<?php
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/banco.php';

$dados_usuario['permissao'] = '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se precisa alterar senha, se precisa ficar redirecionando o usuario para tela de alterar senha
if (isset($_SESSION['precisa_alterar_senha']) && $_SESSION['precisa_alterar_senha'] === 1) {
    // Permitir acesso apenas à página de alteração de senha
    $arquivo_atual = basename($_SERVER['SCRIPT_NAME']);
    if ($arquivo_atual !== 'alterar_senha.php') {
        header('Location: '.$url_base.'/views/user/alterar_senha.php');
        exit;
    }
}

// =========================================================================
// ROTEADOR — mapa de URL amigável => arquivo de conteúdo (dentro de views/)
// 'auth'  => true exige usuário logado
// 'admin' => true exige permissao = 'Admin'
//
// Para adicionar uma página nova ao roteador, basta acrescentar uma linha aqui;
// não precisa mexer em mais nada.
// =========================================================================
$paginas_disponiveis = [
    'inicio'                => ['arquivo' => 'inicio',              'auth' => false, 'admin' => false],

    'materiais'             => ['arquivo' => 'material/index',      'auth' => true,  'admin' => false],
    'materiais/novo'        => ['arquivo' => 'material/create',     'auth' => true,  'admin' => false],
    'materiais/editar'      => ['arquivo' => 'material/edit',       'auth' => true,  'admin' => false],

    'clientes'              => ['arquivo' => 'cliente/index',       'auth' => true,  'admin' => false],
    'clientes/novo'         => ['arquivo' => 'cliente/create',      'auth' => true,  'admin' => true],
    'clientes/editar'       => ['arquivo' => 'cliente/edit',        'auth' => true,  'admin' => true],

    'fornecedores'          => ['arquivo' => 'fornecedor/index',    'auth' => true,  'admin' => false],
    'fornecedores/novo'     => ['arquivo' => 'fornecedor/create',   'auth' => true,  'admin' => true],
    'fornecedores/editar'   => ['arquivo' => 'fornecedor/edit',     'auth' => true,  'admin' => true],

    'balanca'               => ['arquivo' => 'pesagem/index',       'auth' => true,  'admin' => false],
    'balanca/listar'        => ['arquivo' => 'pesagem/listar',      'auth' => true,  'admin' => false],
    'balanca/detalhe'       => ['arquivo' => 'pesagem/pesagem',     'auth' => true,  'admin' => false],
    'balanca/relatorio'     => ['arquivo' => 'pesagem/relatorio',   'auth' => true,  'admin' => false],

    'usuarios'              => ['arquivo' => 'user/admin',          'auth' => true,  'admin' => true],
    'usuarios/novo'         => ['arquivo' => 'user/register',       'auth' => true,  'admin' => true],
    'usuarios/editar'       => ['arquivo' => 'user/edit',           'auth' => true,  'admin' => true],

    'perfil'                => ['arquivo' => 'user/perfil',         'auth' => true,  'admin' => false],
    'calendario'            => ['arquivo' => 'user/calendario',     'auth' => true,  'admin' => false],
];

$pagina = $_GET['pagina'] ?? 'inicio';
$pagina = trim($pagina, '/');

$config = $paginas_disponiveis[$pagina] ?? null;

// Página desconhecida (ou removida da lista) cai na inicial, igual ao padrão que você mandou
if ($config === null) {
    $pagina = 'inicio';
    $config = $paginas_disponiveis['inicio'];
}

// Exige login
if ($config['auth'] && empty($_SESSION['logado'])) {
    header('Location: '.$url_base.'/views/user/login.php');
    exit;
}

// Exige perfil Admin
if ($config['admin'] && (($_SESSION['permissao'] ?? '') !== 'Admin')) {
    header('Location: '.$url_base.'/?msgErro=' . urlencode('Acesso restrito a administradores.'));
    exit;
}

$arquivo_conteudo = __DIR__ . '/views/' . $config['arquivo'] . '.php';

// Sinaliza para sidebar.php que é o roteador quem vai fechar a página (evita fechar </html> cedo demais)
$router_managed = true;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reciclagem</title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/filepond/dist/filepond.min.css" rel="stylesheet" />
    <link href="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://unpkg.com/filepond/dist/filepond.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-file-encode/dist/filepond-plugin-file-encode.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-file-validate-type/dist/filepond-plugin-file-validate-type.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-image-exif-orientation/dist/filepond-plugin-image-exif-orientation.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-image-preview/dist/filepond-plugin-image-preview.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-image-crop/dist/filepond-plugin-image-crop.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-image-resize/dist/filepond-plugin-image-resize.min.js"></script>
    <script src="https://unpkg.com/filepond-plugin-image-transform/dist/filepond-plugin-image-transform.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js" integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q" crossorigin="anonymous"></script>

    <style>
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 250px;
            background-color: #1c1c1c;
            color: white;
            padding: 1rem;
            overflow-y: auto;
        }

        .content {
            margin-left: 250px;
            flex: 1;
            padding: 1rem;
        }

        html, body {
            height: 100%;
        }

        body {
            display: flex;
            flex-direction: column;
        }

        td {
        border: 4px solid #333;
        width: 200px;
        }

        thead,
        tfoot {
        background-color: #333;
        color: #fff;
        }

        main {
            flex: 1;
        }

        .nav-link:hover {
            color:rgb(6, 87, 248) !important;
        }

        select {
        color:#333
        }

        .scFormPage .select2-container .select2-dropdown {
            border-color:rgb(255, 255, 255) !important;
        }

        .select2-dropdown {
            border-radius: 0 0 10px 10px !important;
            overflow: hidden !important;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            border-color:rgb(255, 255, 255) ;
            border-radius: 10px !important;
        }

        .filepond--drop-label {
            color: #4c4e53;
        }

        .filepond--label-action {
            text-decoration-color: #babdc0;
        }

        .filepond--panel-root {
            background-color: #edf0f4;
        }

        .filepond--root {
        max-width: 100px;
        font-size: 12px;
        flex: auto;
        margin-left: auto;
        margin-right: auto;
        display: block;
        margin-bottom: 0%;
        }
    </style>
</head>

<body>
    <svg xmlns="http://www.w3.org/2000/svg" class="d-none"></svg>

    <main class="d-flex flex-nowrap">
<?php
include __DIR__ . '/components/sidebar.php';

if (is_file($arquivo_conteudo)) {
    include $arquivo_conteudo;
} else {
    include __DIR__ . '/views/inicio.php';
}

?>

        </div>

</body>
</html>
