# Balancete DRE — Sistema de Controle Gerencial

Aplicação web para importar balancetes mensais (TXT/RTF/DOC) e gerar uma DRE gerencial interativa.

**Stack:** PHP 8.2+, MySQL/MariaDB, PDO, Bootstrap 5, JavaScript vanilla.  
**Compatível com:** cPanel, hospedagem compartilhada Apache com mod_rewrite.

---

## Estrutura de Pastas

```
/
├── app/
│   ├── Controllers/       # AuthController, DreController, ImportController…
│   ├── Services/          # BalanceteParser, DreCalculator, CsvExporter
│   ├── Views/             # Templates PHP (layout, auth, dashboard, dre…)
│   └── helpers.php        # Funções globais (url, flash, csrf, auth…)
├── config/
│   ├── app.php            # ⚙️ Configurações gerais (fuso, constantes)
│   └── database.php       # ⚙️ Credenciais do banco de dados ← EDITE AQUI
├── database/
│   ├── install.sql        # Estrutura das tabelas
│   └── seed.sql           # Dados iniciais (usuário admin, empresa, DRE, mapeamentos)
├── public/                # ← Document Root do cPanel
│   ├── index.php          # Front controller
│   ├── .htaccess          # Rewrite rules (opcional, melhora URLs)
│   └── assets/
│       ├── css/app.css
│       └── js/app.js
└── storage/
    ├── uploads/           # Arquivos enviados (protegidos do acesso público)
    └── exports/           # CSVs gerados
```

---

## Instalação no cPanel

### 1. Upload dos arquivos

1. Acesse o **Gerenciador de Arquivos** do cPanel ou use FTP.
2. Faça upload de **todo o conteúdo** desta pasta para o servidor.
3. Configure o **Document Root** do domínio/subdomínio para apontar para a pasta `public/`.
   - Em *Domínios → Domínios Adicionais*, aponte o caminho para `…/public`.

> **Alternativa (sem alterar Document Root):** Se não puder alterar o Document Root, coloque todo o conteúdo dentro de `public_html/balancete/` e acesse via `seudominio.com/balancete/index.php`.

### 2. Criar o banco de dados MySQL

1. No cPanel → **Bancos de Dados MySQL**:
   - Crie o banco (ex: `meuusuario_balancete`).
   - Crie um usuário MySQL com senha segura.
   - Associe o usuário ao banco com **Todos os Privilégios**.

2. Importe os arquivos SQL no **phpMyAdmin**:
   - Selecione o banco recém-criado.
   - Aba **Importar** → selecione `database/install.sql` → Execute.
   - Repita para `database/seed.sql`.

### 3. Editar as configurações

Edite `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'meuusuario_balancete');
define('DB_USER', 'meuusuario_dbuser');
define('DB_PASS', 'senha_super_segura');
```

Edite `config/app.php` — troque a APP_KEY:

```php
define('APP_KEY', 'coloque-aqui-uma-string-aleatoria-longa-com-32-ou-mais-caracteres');
```

### 4. Permissões de pasta

Via SSH ou Gerenciador de Arquivos, garanta:

```bash
chmod 755 storage/
chmod 755 storage/uploads/
chmod 755 storage/exports/
```

### 5. Primeiro acesso

1. Acesse `https://seudominio.com` (ou `…/public/index.php`).
2. Clique em **"Configure o administrador"** (link na tela de login).
3. Crie a conta de administrador.
4. Faça login e **troque a senha** imediatamente.

---

## Criar o administrador

O `seed.sql` **não** inclui usuário. Na primeira visita ao sistema:

1. Acesse `https://seudominio.com/index.php?route=setup`
2. Preencha nome, e-mail e senha do administrador.
3. Clique em **Criar administrador**.
4. Faça login normalmente.

Após criar o primeiro admin, a rota `/setup` fica bloqueada automaticamente.

---

## Fluxo de uso

1. **Login** → Dashboard
2. **Importações → Nova Importação** → Upload do balancete (TXT/RTF/DOC)
3. Sistema exibe **preview** com DRE calculada e contas não mapeadas
4. **Confirmar** a importação → dados disponíveis na DRE
5. **DRE** → filtrar por empresa, unidade, ano, meses → visualizar tabela interativa
6. Clicar em qualquer linha da DRE → ver contas contábeis de origem
7. **Ajustes** → criar ajustes manuais com justificativa
8. **Mapeamentos** (admin) → definir/editar quais códigos alimentam cada linha da DRE
9. **Exportar CSV** → botão na tela DRE

---

## Regras de negócio — Parser do Balancete

- **Somente a coluna Movimento** é utilizada nos cálculos.
- Débito e Crédito são lidos e armazenados apenas para auditoria.
- Linhas com código de sub-unidade (ex: `001 Receita ...`) são marcadas como **analíticas** e ignoradas por padrão (evita duplicidade).
- O sinal do valor é determinado pelo campo `sign_behavior` da linha DRE:
  - `revenue / op_revenue / fin_revenue` → CR = positivo, DB = negativo
  - `cost / expense / fin_expense / tax / deduction` → DB = positivo, CR = negativo

---

## Troubleshooting

| Problema | Solução |
|----------|---------|
| Tela em branco / erro 500 | Verifique `config/database.php` (credenciais corretas?) |
| Upload não funciona | Verifique permissão 755 em `storage/uploads/` |
| URLs 404 | Verifique se `mod_rewrite` está ativo. Tente acessar diretamente via `index.php?route=login` |
| Caracteres estranhos no balancete | O parser converte ISO-8859-1 → UTF-8. Se não funcionar, salve o arquivo em UTF-8 antes de importar |
| DRE sem valores | Verifique mapeamentos em **Mapeamentos** e se a importação foi **confirmada** |
| Erro "Token CSRF inválido" | Sessão expirou — faça login novamente |

---

## Requisitos do servidor

- PHP **8.2+** com extensões: `pdo`, `pdo_mysql`, `mbstring`, `fileinfo`
- MySQL **5.7+** ou MariaDB **10.4+**
- Apache com `mod_rewrite` (opcional mas recomendado para URLs limpas)
- Mínimo 64 MB de memória PHP (`memory_limit = 64M`)
- `upload_max_filesize` ≥ 20M, `post_max_size` ≥ 20M

---

## Segurança

- Senhas armazenadas com `password_hash()` / `PASSWORD_DEFAULT` (bcrypt).
- CSRF token em todos os formulários POST.
- Pastas `app/`, `config/`, `database/`, `storage/` protegidas com `.htaccess` (`Deny from all`).
- Arquivos enviados salvos fora do Document Root.
- Sessões com `httponly` e `samesite=Lax`.
