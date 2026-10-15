<?php
// API de progresso do aluno: GET busca as respostas pelo email, POST grava (insere ou atualiza).
declare(strict_types=1);

ini_set('display_errors', '0');

const APOSTILAS_PERMITIDAS = ['repaso-a1-a2'];
const TAMANHO_MAXIMO_CORPO = 262144;
const TAMANHO_MAXIMO_TEXTO = 150;
const TAMANHO_MAXIMO_EMAIL = 190;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function responder(int $status, array $corpo = []): void
{
    http_response_code($status);
    echo json_encode($corpo ?: new stdClass(), JSON_UNESCAPED_UNICODE);
    exit;
}

function conectar(): PDO
{
    $arquivoConfig = __DIR__ . '/config.php';
    if (!is_file($arquivoConfig)) {
        throw new RuntimeException('Arquivo api/config.php não encontrado.');
    }
    require_once $arquivoConfig;

    return new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function normalizarEmail($valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $email = strtolower(trim($valor));
    if (strlen($email) > TAMANHO_MAXIMO_EMAIL) {
        return null;
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
}

function validarApostila($valor): ?string
{
    return is_string($valor) && in_array($valor, APOSTILAS_PERMITIDAS, true) ? $valor : null;
}

function validarTexto($valor): ?string
{
    if ($valor === null) {
        return '';
    }
    if (!is_string($valor)) {
        return null;
    }
    $texto = trim($valor);
    return mb_strlen($texto) <= TAMANHO_MAXIMO_TEXTO ? $texto : null;
}

function validarDatos($valor): ?array
{
    if (!is_array($valor) || !is_array($valor['values'] ?? null)) {
        return null;
    }
    foreach ($valor['values'] as $resposta) {
        if (!is_string($resposta)) {
            return null;
        }
    }
    return [
        'values' => (object) $valor['values'],
        'screen' => is_string($valor['screen'] ?? null) ? $valor['screen'] : 'intro',
        'savedAt' => is_string($valor['savedAt'] ?? null) ? $valor['savedAt'] : '',
    ];
}

function lerCorpoJson(): ?array
{
    $corpo = file_get_contents('php://input', false, null, 0, TAMANHO_MAXIMO_CORPO + 1);
    if ($corpo === false || strlen($corpo) > TAMANHO_MAXIMO_CORPO) {
        return null;
    }
    $dados = json_decode($corpo, true);
    return is_array($dados) ? $dados : null;
}

function buscar(PDO $pdo): void
{
    $email = normalizarEmail($_GET['email'] ?? null);
    $apostila = validarApostila($_GET['apostila'] ?? null);
    if ($email === null || $apostila === null) {
        responder(400, ['erro' => 'Email ou apostila inválidos.']);
    }

    $consulta = $pdo->prepare(
        'SELECT nombre, profesor, datos, actualizado_en FROM progreso_alumno WHERE email = ? AND apostila = ?'
    );
    $consulta->execute([$email, $apostila]);
    $registro = $consulta->fetch();
    if ($registro === false) {
        responder(404);
    }

    responder(200, [
        'nombre' => $registro['nombre'],
        'profesor' => $registro['profesor'],
        'datos' => json_decode($registro['datos']),
        'actualizadoEn' => $registro['actualizado_en'],
    ]);
}

function gravar(PDO $pdo): void
{
    $corpo = lerCorpoJson();
    if ($corpo === null) {
        responder(400, ['erro' => 'Corpo da requisição inválido ou muito grande.']);
    }

    $email = normalizarEmail($corpo['email'] ?? null);
    $apostila = validarApostila($corpo['apostila'] ?? null);
    $nombre = validarTexto($corpo['nombre'] ?? null);
    $profesor = validarTexto($corpo['profesor'] ?? null);
    $datos = validarDatos($corpo['datos'] ?? null);
    if ($email === null || $apostila === null || $nombre === null || $profesor === null || $datos === null) {
        responder(422, ['erro' => 'Dados do progresso inválidos.']);
    }

    // VALUES() mantém compatibilidade com MariaDB, usado em parte dos planos da Hostinger.
    $pdo->prepare(
        'INSERT INTO progreso_alumno (email, apostila, nombre, profesor, datos) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), profesor = VALUES(profesor), datos = VALUES(datos)'
    )->execute([$email, $apostila, $nombre, $profesor, json_encode($datos, JSON_UNESCAPED_UNICODE)]);

    responder(200, ['ok' => true]);
}

try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($metodo === 'GET') {
        buscar(conectar());
    }
    if ($metodo === 'POST') {
        gravar(conectar());
    }
    header('Allow: GET, POST');
    responder(405, ['erro' => 'Método não permitido.']);
} catch (Throwable $erro) {
    error_log('Falha na API de progresso do aluno: ' . $erro->getMessage());
    responder(500, ['erro' => 'Erro interno ao processar o progresso.']);
}
