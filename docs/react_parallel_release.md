# React Parallel Release

## Objetivo
- Validar a nova interface React em paralelo ao legado PHP sem substituir a aplicacao atual em `/`.
- Manter o backend em PHP, a sessao existente e as regras sensiveis no servidor.
- Publicar a nova experiencia em `/newrelease` com API JSON em `/api`.

## Estrategia adotada
- O legado permanece intacto:
  - `index.php` continua sendo a entrada principal;
  - `public_html/index.php` continua carregando o bootstrap atual;
  - nenhuma regra de negocio foi movida para o frontend.
- A nova release foi adicionada em paralelo:
  - fonte React/Vite em `frontend/newrelease`;
  - build estatico em `public_html/newrelease`;
  - API PHP em `public_html/api`.

## Estrutura segura aplicada
```text
project-root/
  index.php
  controllers/
  models/
  includes/
  public_html/
    index.php
    /api
    /newrelease
  frontend/
    /newrelease
```

## Endpoints iniciais da API
- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`
- `GET /api/dashboard/summary`
- `GET /api/accounts`
- `GET /api/categories`
- `GET /api/transactions`
- `GET /api/targets/summary`

## Padroes aplicados
- Toda resposta segue:
```json
{
  "success": true,
  "message": "Texto objetivo",
  "data": {},
  "errors": []
}
```
- Autenticacao continua por sessao PHP.
- O React consome a API com `credentials: include`.
- `GET /api/me` entrega `csrf_token` para login/logout via frontend React.
- `current_user_id()` e escopo admin continuam sendo resolvidos no backend.

## Build e publicacao
- Desenvolvimento local:
  - instalar dependencias em `frontend/newrelease`;
  - executar `node node_modules/vite/bin/vite.js build` ou `npm run build` quando o Node estiver disponivel;
  - o build e enviado para `public_html/newrelease`.
- Producao Hostinger:
  - publicar `public_html/newrelease` como assets estaticos;
  - publicar `public_html/api` como nova entrada JSON;
  - manter `/` apontando para o legado.

## .htaccess necessario
- `public_html/api/.htaccess`
  - roteia chamadas amigaveis da API para `public_html/api/index.php`.
- `public_html/newrelease/.htaccess`
  - garante fallback SPA para `index.html` em `/newrelease`.

## Validacao controlada sugerida
- Liberar o piloto apenas para admin ou usuarios internos no inicio.
- Testar:
  - login e logout;
  - navegacao entre rotas React;
  - sessao compartilhada entre `/` e `/newrelease`;
  - escopo admin/user;
  - leitura de assets em `/newrelease/assets`;
  - respostas da API em `/api/*`.

## Modelo de virada futura
- Fase atual:
  - legado em `/`;
  - React em `/newrelease`.
- Fase futura opcional:
  - promover React para `/`;
  - manter legado em uma rota de contingencia;
  - rollback = restaurar o apontamento de `/` para o legado e manter `/newrelease` como piloto.

