# repaso-a1-a2
Apostila interativa de repaso de español A1–A2 de LeoEspanhol, con 7 unidades, guardado de progreso y envío al profesor.

## Progresso online (por email)

O aluno informa o email (primeiro campo da tela inicial) e a página busca no servidor as respostas que ele já
preencheu, em qualquer dispositivo. A partir daí, o progresso é gravado automaticamente (3 s após parar de
escrever, e também em "Guardar progreso" e "Enviar al profesor"). O `localStorage` continua como cópia offline.

| Arquivo | Função |
|---|---|
| `index.html` | Página da apostila (sincronização em `lookupEmail()` / `pushRemote()`) |
| `api/progreso.php` | API: `GET ?email=&apostila=` busca, `POST` grava (insere ou atualiza) |
| `api/schema.sql` | Tabela `progreso_alumno` |
| `api/config.example.php` | Modelo das credenciais do banco |

> A identificação é só pelo email: quem souber o email de um aluno consegue ver e alterar as respostas dele.

### Deploy na Hostinger (hPanel)

Passo a passo detalhado para quem não é desenvolvedor: [docs/GUIA-HOSTINGER.md](docs/GUIA-HOSTINGER.md).

1. **Bancos de dados → Bancos de dados MySQL**: crie o banco e o usuário.
2. **phpMyAdmin**: selecione o banco e execute o conteúdo de `api/schema.sql`.
3. Publique os arquivos do repositório (hPanel → **Avançado → Git**, apontando para este repositório e a branch `main`, ou por FTP / Gerenciador de Arquivos).
4. **Gerenciador de Arquivos**: dentro da pasta `api/` publicada, copie `config.example.php` para `config.php` e preencha com os dados do banco criado no passo 1. Esse arquivo está no `.gitignore` e não deve ir para o GitHub.
5. Teste: abra `https://SEU-DOMINIO/api/progreso.php?apostila=repaso-a1-a2&email=teste@exemplo.com` — deve responder `{}` com status 404 (aluno ainda não existe).

Se algo falhar, a API responde `500` e registra o detalhe no log de erros do PHP (hPanel → **Avançado → Logs de erros**).
