# Progresso do aluno no MySQL da Hostinger (recuperar pelo email)

## Contexto

Hoje a apostila (`index.html`, um único arquivo de ~2 MB) guarda as respostas só no `localStorage`
do navegador (`save()` / `restore()` em `index.html:848-882`, chave `leoespanhol_repaso_a1a2_v2`).
Se o aluno trocar de computador ou navegador, perde tudo. O objetivo: ao informar o **email**, a
página busca no servidor o que ele já preencheu e preenche o formulário; a partir daí, grava
automaticamente no servidor. Para isso o campo de email passa a ser o **primeiro** (antes do nome).

Decisões já tomadas:
- **Hospedagem:** Hostinger hPanel com PHP + MySQL → endpoint PHP no mesmo domínio (sem CORS, sem serviço externo).
- **Identificação:** só o email (sem PIN). Risco aceito: quem souber o email de outro aluno vê/sobrescreve as respostas dele.
- **Gravação:** automática (debounce ~3 s) + botões "Guardar progreso" e "Enviar al profesor". `localStorage` continua como cópia offline.

## Arquitetura

```
index.html (navegador)  --GET/POST JSON-->  api/progreso.php  --PDO-->  MySQL (tabela progreso_alumno)
                                               └─ require api/config.php (credenciais, fora do git)
```

## Arquivos

| Arquivo | Ação |
|---|---|
| `api/schema.sql` | novo — DDL da tabela (rodar uma vez no phpMyAdmin) |
| `api/progreso.php` | novo — endpoint GET (buscar) / POST (upsert) |
| `api/config.example.php` | novo — modelo de configuração (sem senha real) |
| `.gitignore` | novo — ignora `api/config.php` |
| `index.html` | editar — ordem dos campos, textos e script de sincronização |
| `README.md` | editar — passo a passo de deploy na Hostinger |

### 1. Banco — `api/schema.sql`

```sql
CREATE TABLE IF NOT EXISTS progreso_alumno (
  email          VARCHAR(190) NOT NULL,
  apostila       VARCHAR(50)  NOT NULL,          -- 'repaso-a1-a2' (permite outras apostilas no futuro)
  nombre         VARCHAR(150) NOT NULL DEFAULT '',
  profesor       VARCHAR(150) NOT NULL DEFAULT '',
  datos          LONGTEXT     NOT NULL,          -- JSON {values:{campo:valor}, screen, savedAt}
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (email, apostila)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
`LONGTEXT` em vez de `JSON` para funcionar igual em MySQL e MariaDB (a Hostinger usa MariaDB em alguns planos).

### 2. API — `api/progreso.php`

- `require __DIR__.'/config.php'` → constantes `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
- PDO `mysql:...;charset=utf8mb4`, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`; **só prepared statements**.
- Resposta sempre `Content-Type: application/json; charset=utf-8`, `Cache-Control: no-store`.
- Funções pequenas com early return: `responder($status, $corpo)`, `normalizarEmail()`, `validarApostila()`, `lerCorpoJson()`, `buscar()`, `gravar()`.
- **GET** `?email=...&apostila=repaso-a1-a2`
  - email inválido → 400; não encontrado → 404 `{}`; encontrado → 200 `{nombre, profesor, datos, actualizadoEn}`.
- **POST** corpo JSON `{email, apostila, nombre, profesor, datos}`
  - Validações: `filter_var(FILTER_VALIDATE_EMAIL)` após `trim`+`mb_strtolower`; `apostila` em lista permitida (`['repaso-a1-a2']`); `nombre`/`profesor` ≤ 150 chars; `datos` objeto com `values` contendo só strings; corpo ≤ 256 KB.
  - `INSERT ... ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), profesor=VALUES(profesor), datos=VALUES(datos)` → 200 `{actualizadoEn}`.
- Outros métodos → 405. Exceções → 500 com mensagem genérica; detalhe vai para `error_log` em **pt-BR** (ex.: `"Falha ao gravar progresso do aluno: ..."`). Nunca vazar mensagem do PDO ao cliente.

### 3. Configuração — `api/config.example.php` + `.gitignore`

Modelo com valores fictícios; o `api/config.php` real é criado manualmente no servidor (Gerenciador de
Arquivos do hPanel) e fica no `.gitignore`, então a senha nunca vai para o GitHub. Um `git pull` do deploy
não apaga arquivos não rastreados.

### 4. Frontend — `index.html`

**Markup (linhas ~243-256):** reordenar `.student-meta` para **Correo → Nombre → Profesor/grupo**; adicionar
`<p id="studentEmailStatus" class="student-help" aria-live="polite">` abaixo do email com a dica
"Escribe tu correo para recuperar tus respuestas en cualquier dispositivo." Ajustar o texto da intro
(linha ~241, "guardado local") e a mensagem de reset para refletir o salvamento online.

**Script (bloco `<script>` em 773-1056), seguindo o estilo existente (IIFE, funções curtas):**

- Constantes: `SETTINGS.apiUrl='api/progreso.php'` (relativo, funciona em subpasta), `APOSTILA='repaso-a1-a2'`.
- Estado: `syncedEmail` = email cujo registro remoto já foi consultado; `remoteTimer` para o debounce.
- `isValidEmail(v)` e `currentEmail()` (trim + lowercase).
- Extrair de `save()` a montagem do objeto em `collectData()` e de `restore()` a aplicação em `applyData(data)` — reaproveitadas pelo local e pelo remoto.
- `lookupEmail()` — disparado no `change`/`blur` do email e no load (se `restore()` trouxe um email):
  1. Email inválido → mensagem e sai.
  2. `GET` na API.
  3. **Encontrado:** se o `localStorage` é do mesmo email **e** `local.savedAt > remoto.datos.savedAt` → mantém o local e envia ao servidor; senão `applyData(remoto)` e status "Respuestas recuperadas (última vez: dd/mm hh:mm)".
  4. **404:** se o local não tinha email ou era o mesmo → mantém as respostas e as associa ao email; se era de **outro** email (computador compartilhado) → limpa os campos e status "Correo nuevo: empieza la apostila".
  5. Falha de rede → status "Sin conexión: tu progreso se guarda solo en este dispositivo" (local segue funcionando).
  6. Ao terminar: `syncedEmail = email`.
- `queueRemoteSave()` — chamado dentro de `save()`; debounce de 3 s → `pushRemote()`.
- `pushRemote({keepalive})` — `POST` só se `currentEmail() === syncedEmail` (**evita sobrescrever dados do servidor com um formulário vazio antes da consulta terminar**).
- `save(true)` (botão flutuante) e `sendToTeacher()` chamam `pushRemote()` imediatamente; o toast passa a dizer "Progreso guardado en la nube · hh:mm" ou "guardado solo en este dispositivo" conforme o resultado.
- `pagehide` / `visibilitychange→hidden` → `pushRemote({keepalive:true})` se houver gravação pendente.
- Mudar o email para outro → `syncedEmail=null` + nova consulta (o listener de `input` existente já chama `save()`; o push fica bloqueado até a consulta).
- `resetAll()` continua só local (não há DELETE na API — com identificação só por email, um DELETE permitiria a qualquer um apagar o trabalho alheio).

### 5. README — deploy na Hostinger

1. hPanel → **Bancos de dados MySQL**: criar banco + usuário.
2. phpMyAdmin → executar `api/schema.sql`.
3. Gerenciador de Arquivos → criar `api/config.php` a partir de `config.example.php` com as credenciais.
4. hPanel → **Avançado → Git**: apontar para `github.com/Leoespanhol/repaso-a1-a2`, branch `main`, e ativar auto-deploy (webhook) — ou enviar por FTP se já é assim que se publica hoje.

## Verificação

Sem PHP local, a validação é no servidor (de preferência numa subpasta de teste, ex. `/teste-repaso/`):

1. `GET api/progreso.php?email=naoexiste@x.com&apostila=repaso-a1-a2` → 404; `?email=lixo` → 400; `PUT` → 405 (via navegador/curl).
2. Navegador A: informar email novo, preencher respostas → após 3 s conferir a linha no phpMyAdmin.
3. Navegador B (ou aba anônima): informar o mesmo email → respostas, nome, profesor e unidade atual aparecem.
4. Computador compartilhado: com respostas do aluno 1 na tela, digitar email novo do aluno 2 → formulário limpo; voltar ao email 1 → dados do aluno 1 voltam.
5. Desligar a rede (DevTools → Offline) → aviso "solo en este dispositivo" e o `localStorage` segue salvando; religar e editar → sincroniza.
6. "Enviar al profesor" continua mandando o Formspree normalmente.
7. Testar acentos/ñ (`utf8mb4`) e um email com maiúsculas (normaliza para minúsculas).

## Ajustes feitos na implementação

- **Sem comparação de horários:** o `restore()` original chamava `show()` → `save()` e marcava `savedAt` como "agora" a cada carregamento, então comparar horários não era confiável. No lugar, o `localStorage` guarda `owner` (email dono das respostas na tela) e `pending` (há alterações ainda não enviadas). Regra: se `owner === email` e `pending`, o local vence; senão, o servidor vence.
- **`show(target, scroll, persist)`:** novo parâmetro `persist` para o `restore()` não gravar ao carregar.
- **Email novo sem respostas na tela:** não limpa nem cria registro vazio; o registro nasce na primeira resposta (evita lixo quando o aluno digita o email errado e corrige).
- **Respostas escritas antes do email + email já existente:** `confirm()` explicando as duas opções (carregar as do servidor ou manter as da tela).
- **Unidade atual:** ao recuperar do servidor, o aluno continua na tela em que está (não salta para a unidade salva).
- **Envios em fila** (`pushChain`): evita que um envio antigo chegue depois de um mais recente.
- **POST** responde `{ok: true}` (o front não usa `actualizadoEn`).
- `maxlength` nos campos de email (190), nome e profesor (150), alinhado com a validação do PHP.
- Validação local feita com um servidor de simulação em Node (7 cenários aprovados); o PHP real ainda precisa ser validado na Hostinger.
