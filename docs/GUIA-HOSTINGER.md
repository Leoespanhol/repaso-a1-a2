# Guia de instalação na Hostinger: salvar o progresso dos alunos

Este guia é para quem vai configurar o site no painel da Hostinger. **Não é preciso saber programar.**
Siga os passos na ordem e marque cada um quando terminar. Leva cerca de 20 minutos.

> **O que esta mudança faz:** o aluno digita o email na primeira tela da apostila e a página traz de volta
> tudo o que ele já respondeu, mesmo em outro computador ou celular.

> **Observação:** os nomes dos menus da Hostinger podem mudar um pouco com o tempo. Se não encontrar um
> menu com o nome exato, procure um nome parecido ou use a busca do painel.

---

## Antes de começar

Você vai precisar de:

- [ ] Login e senha do painel da Hostinger (**hPanel**: https://hpanel.hostinger.com).
- [ ] Os arquivos novos da apostila, enviados pelo desenvolvedor (ou a confirmação de que eles já estão no GitHub, na branch `main`):
  - `index.html`
  - a pasta `api`, com os arquivos `progreso.php`, `schema.sql` e `config.example.php`
- [ ] Um bloco de notas aberto para anotar as informações dos Passos 1 e 2.

---

## Passo 1: criar o banco de dados

O banco de dados é onde as respostas dos alunos ficarão guardadas.

1. Entre no hPanel.
2. No menu **Sites**, clique em **Painel** (ou **Gerenciar**) ao lado do site da apostila.
3. No menu à esquerda, abra **Bancos de dados** e clique em **Gerenciamento** (ou **Bancos de dados MySQL**).
4. Em **Criar um novo banco de dados MySQL**, preencha:
   - **Nome do banco de dados:** `leoespanhol`
   - **Nome de usuário do banco de dados:** `leoespanhol`
   - **Senha:** crie uma senha forte (ou clique no botão de gerar senha).
5. Clique em **Criar**.
6. **Anote no bloco de notas** os três dados exatamente como aparecem na lista abaixo do formulário.
   A Hostinger coloca um prefixo com números na frente do nome, por exemplo:

   | O que anotar | Exemplo |
   |---|---|
   | Nome do banco | `u123456789_leoespanhol` |
   | Usuário | `u123456789_leoespanhol` |
   | Senha | a senha que você criou |

> ⚠️ Guarde a senha em um lugar seguro e **não a envie por email ou WhatsApp**.

---

## Passo 2: criar a tabela no banco de dados

1. Na mesma tela de **Bancos de dados → Gerenciamento**, na lista de bancos, encontre o banco que você
   acabou de criar e clique em **Entrar no phpMyAdmin** (ou nos três pontinhos → **phpMyAdmin**).
2. Uma nova aba se abre. Na coluna da esquerda, clique no nome do banco (`u123456789_leoespanhol`).
3. No topo, clique na aba **SQL**.
4. Copie **todo** o texto abaixo e cole na caixa grande:

   ```sql
   CREATE TABLE IF NOT EXISTS progreso_alumno (
     email          VARCHAR(190) NOT NULL,
     apostila       VARCHAR(50)  NOT NULL,
     nombre         VARCHAR(150) NOT NULL DEFAULT '',
     profesor       VARCHAR(150) NOT NULL DEFAULT '',
     datos          LONGTEXT     NOT NULL COMMENT 'JSON {values, screen, savedAt}',
     creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
     actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
     PRIMARY KEY (email, apostila)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

5. Clique em **Executar** (ou **Go**), no canto inferior direito.
6. ✅ Deve aparecer uma mensagem verde de sucesso. Na coluna da esquerda, embaixo do banco, aparece a
   tabela **progreso_alumno**.
7. Pode fechar a aba do phpMyAdmin.

---

## Passo 3: enviar os arquivos novos para o site

Escolha **uma** das opções abaixo.

### Opção A: o site é atualizado pelo GitHub (Git)

Use esta opção se, no hPanel, em **Avançado → Git**, já existe um repositório ligado ao site.

1. Confirme com o desenvolvedor que as alterações já estão na branch **`main`** do GitHub.
2. No hPanel, abra **Avançado → Git**.
3. Na lista de repositórios, clique em **Implantar** (ou **Deploy**, **Puxar**).
4. ✅ Aguarde a mensagem de sucesso.

### Opção B: enviar os arquivos manualmente (Gerenciador de arquivos)

1. No hPanel, abra **Arquivos → Gerenciador de arquivos**.
2. Entre na pasta onde está a apostila hoje: geralmente é **`public_html`**, ou uma subpasta dela.
   Você reconhece a pasta certa porque ela tem o arquivo **`index.html`** da apostila.
3. **Faça uma cópia de segurança:** clique com o botão direito no `index.html` atual → **Renomear** →
   `index-antigo.html`.
4. Clique no botão **Upload** (ícone de nuvem com seta) e envie o **`index.html` novo**.
5. Ainda na mesma pasta, envie a pasta **`api`** inteira. Se o painel não aceitar pastas:
   1. Crie uma pasta nova chamada `api` (botão **Nova pasta**).
   2. Entre nela e envie os três arquivos: `progreso.php`, `schema.sql` e `config.example.php`.
6. ✅ No fim, a pasta da apostila deve ter o `index.html` e a pasta `api` lado a lado.

---

## Passo 4: informar ao site os dados do banco

Este é o passo mais importante: é aqui que o site aprende a senha do banco.

1. No **Gerenciador de arquivos**, entre na pasta **`api`** (a que fica junto do `index.html`).
2. Clique com o botão direito em **`config.example.php`** → **Copiar**, e cole na mesma pasta.
   Depois **renomeie a cópia** para exatamente: `config.php`
   - Outra forma: clique em **Novo arquivo**, digite o nome `config.php` e cole o conteúdo do item 4 abaixo.
3. Clique duas vezes em **`config.php`** para abrir o editor.
4. Deixe o conteúdo **exatamente assim**, trocando só o que está entre aspas pelos dados anotados no Passo 1:

   ```php
   <?php
   const DB_HOST = 'localhost';
   const DB_NAME = 'u123456789_leoespanhol';
   const DB_USER = 'u123456789_leoespanhol';
   const DB_PASS = 'a-senha-que-voce-criou';
   ```

   **Cuidados:**
   - Mantenha as aspas simples `' '` em volta de cada valor.
   - Mantenha o `;` no final de cada linha.
   - A linha `DB_HOST` continua sendo `localhost`.
   - Não deixe espaços antes ou depois do texto, dentro das aspas.
5. Clique em **Salvar** (ícone de disquete, no canto superior direito).

---

## Passo 5: testar

### Teste 1: o servidor está respondendo

No navegador, abra o endereço abaixo, trocando `SEU-SITE.com` pelo endereço da apostila
(inclua a subpasta, se a apostila estiver em uma):

```
https://SEU-SITE.com/api/progreso.php?apostila=repaso-a1-a2&email=teste@exemplo.com
```

| O que aparece | Significado |
|---|---|
| `{}` | ✅ Tudo certo. Siga para o Teste 2. |
| `{"erro":"Erro interno ao processar o progresso."}` | ❌ Dados do banco errados no `config.php`. Volte ao Passo 4 e confira letra por letra. |
| Página de erro 404 / "não encontrado" | ❌ A pasta `api` não está no lugar certo. Volte ao Passo 3. |
| Aparece um texto que começa com `<?php` | ❌ Fale com o suporte da Hostinger: o PHP não está ativo no site. |

### Teste 2: a apostila guarda as respostas

1. Abra a apostila no computador.
2. Na primeira tela, o **primeiro campo** agora é **Correo del alumno**. Digite um email de teste
   (por exemplo `teste1@exemplo.com`) e clique fora do campo.
3. Embaixo do campo, deve aparecer: *"Correo nuevo: tu progreso se guardará en línea."*
4. Preencha o nome e responda duas ou três perguntas da Unidade 1.
5. Espere 5 segundos. Embaixo do email deve aparecer: *"Progreso guardado en línea · (hora)"*.
6. Agora abra a apostila **no celular** (ou em uma janela anônima do navegador) e digite o mesmo email.
7. ✅ O nome e as respostas devem aparecer, com a mensagem *"Respuestas recuperadas"*.

Se os dois testes deram certo, **a instalação terminou**. 🎉

---

## Se algo der errado

| Problema | O que fazer |
|---|---|
| Aparece *"Sin conexión con el servidor…"* embaixo do email | Faça o Teste 1. O resultado dele indica o que corrigir. |
| Esqueci a senha do banco | Em **Bancos de dados → Gerenciamento**, use a opção de alterar a senha do usuário e atualize o `config.php` (Passo 4). |
| Quero desfazer tudo | No Gerenciador de arquivos, apague o `index.html` novo e renomeie o `index-antigo.html` de volta para `index.html`. |
| Quero ver as respostas salvas | Abra o phpMyAdmin (Passo 2), clique na tabela **progreso_alumno**. Cada linha é um aluno. **Não edite nem apague linhas por lá**: isso apaga o trabalho do aluno. |

> **Segurança:** nunca envie o arquivo `config.php` para ninguém e nunca o coloque no GitHub. Ele contém a senha do banco.
