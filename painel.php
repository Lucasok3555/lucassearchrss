<?php
session_start();

$data_file = 'rss_data.json';
$default_password = '12345678';
$admin_password = isset($_SESSION['password']) ? $_SESSION['password'] : null;

// Inicializar arquivo de dados
if (!file_exists($data_file)) {
    file_put_contents($data_file, json_encode([
        'sources' => [],
        'articles' => []
    ]));
}

// Ler dados
function readData() {
    global $data_file;
    return json_decode(file_get_contents($data_file), true);
}

// Salvar dados
function saveData($data) {
    global $data_file;
    file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT));
}

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'get_data') {
        echo json_encode(readData());
        exit;
    }

    if ($_GET['action'] === 'logout') {
        session_destroy();
        header('Location: painel.php');
        exit;
    }
}

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        if ($_POST['password'] === $default_password) {
            $_SESSION['password'] = $default_password;
            header('Location: painel.php');
            exit;
        } else {
            $error = 'Senha incorreta!';
        }
    }

    if ($admin_password === $default_password) {
        if (isset($_POST['add_rss'])) {
            $data = readData();
            $feed_url = $_POST['feed_url'];
            $feed_name = $_POST['feed_name'];

            // Buscar artigos do RSS
            $articles = [];
            if (!empty($feed_url)) {
                try {
                    $xml = simplexml_load_file($feed_url);
                    if ($xml && isset($xml->channel->item)) {
                        foreach ($xml->channel->item as $item) {
                            $articles[] = [
                                'id' => md5((string)$item->link),
                                'title' => (string)$item->title,
                                'description' => strip_tags((string)$item->description),
                                'link' => (string)$item->link,
                                'date' => (string)$item->pubDate,
                                'image' => null,
                                'source' => $feed_name ?: (string)$xml->channel->title
                            ];
                        }
                    }
                } catch (Exception $e) {
                    // Erro ao carregar RSS
                }
            }

            if (!in_array($feed_name, $data['sources'])) {
                $data['sources'][] = $feed_name;
            }

            $data['articles'] = array_merge($data['articles'], $articles);
            // Remove duplicatas
            $data['articles'] = array_values(array_unique(array_map('json_encode', $data['articles']), SORT_REGULAR));
            $data['articles'] = array_map('json_decode', $data['articles'], array_fill(0, count($data['articles']), true));

            saveData($data);
            $success = 'RSS adicionado com sucesso!';
        }

        if (isset($_POST['delete_source'])) {
            $data = readData();
            $source = $_POST['delete_source'];
            $data['articles'] = array_filter($data['articles'], function($a) use ($source) {
                return $a['source'] !== $source;
            });
            $data['articles'] = array_values($data['articles']);
            $data['sources'] = array_filter($data['sources'], function($s) use ($source) {
                return $s !== $source;
            });
            $data['sources'] = array_values($data['sources']);
            saveData($data);
            $success = 'Fonte removida com sucesso!';
        }

        if (isset($_POST['edit_article'])) {
            $data = readData();
            $article_id = $_POST['article_id'];
            foreach ($data['articles'] as &$article) {
                if ($article['id'] === $article_id) {
                    $article['title'] = $_POST['title'];
                    $article['description'] = $_POST['description'];
                    break;
                }
            }
            saveData($data);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        if (isset($_POST['delete_article'])) {
            $data = readData();
            $article_id = $_POST['delete_article'];
            $data['articles'] = array_filter($data['articles'], function($a) use ($article_id) {
                return $a['id'] !== $article_id;
            });
            $data['articles'] = array_values($data['articles']);
            saveData($data);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

$data = readData();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - RSS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .login-box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 90%;
        }

        .login-box h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .login-btn:hover {
            background: #5568d3;
        }

        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background: #fadbd8;
            border-radius: 8px;
        }

        .success {
            color: #27ae60;
            padding: 15px;
            background: #d5f4e6;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .dashboard {
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .header h1 {
            color: #333;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            resize: vertical;
            min-height: 100px;
            transition: border-color 0.3s;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 1em;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .sources-list {
            margin-top: 20px;
        }

        .source-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }

        .source-info {
            flex: 1;
        }

        .source-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .source-count {
            color: #999;
            font-size: 0.9em;
        }

        .articles-list {
            margin-top: 20px;
        }

        .article-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }

        .article-info {
            flex: 1;
        }

        .article-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .article-meta {
            color: #999;
            font-size: 0.9em;
        }

        .article-actions {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 0.9em;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #999;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .article-item, .source-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .article-actions {
                width: 100%;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>

<?php if ($admin_password !== $default_password): ?>
    <div class="login-container">
        <div class="login-box">
            <h2>🔐 Painel Admin</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="password">Senha:</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                <button type="submit" name="login" class="login-btn">Entrar</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="dashboard">
        <div class="header">
            <h1>📊 Painel de Controle RSS</h1>
            <a href="?action=logout" class="logout-btn">Sair</a>
        </div>

        <?php if (isset($success)): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <!-- Adicionar RSS -->
        <div class="section">
            <h2>➕ Adicionar Novo RSS</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="feed_name">Nome da Fonte:</label>
                        <input type="text" id="feed_name" name="feed_name" placeholder="Ex: TechCrunch" required>
                    </div>
                    <div class="form-group">
                        <label for="feed_url">URL do Feed RSS:</label>
                        <input type="url" id="feed_url" name="feed_url" placeholder="Ex: https://feeds...." required>
                    </div>
                </div>
                <button type="submit" name="add_rss" class="btn btn-primary">Adicionar RSS</button>
            </form>
        </div>

        <!-- Fontes -->
        <div class="section">
            <h2>📁 Fontes RSS</h2>
            <?php if (empty($data['sources'])): ?>
                <p style="color: #999;">Nenhuma fonte adicionada ainda.</p>
            <?php else: ?>
                <div class="sources-list">
                    <?php foreach ($data['sources'] as $source): 
                        $count = count(array_filter($data['articles'], function($a) use ($source) {
                            return $a['source'] === $source;
                        }));
                    ?>
                        <div class="source-item">
                            <div class="source-info">
                                <div class="source-name"><?= htmlspecialchars($source) ?></div>
                                <div class="source-count"><?= $count ?> artigos</div>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="delete_source" value="<?= htmlspecialchars($source) ?>">
                                <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Tem certeza?')">Remover</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Artigos -->
        <div class="section">
            <h2>📰 Artigos (<?= count($data['articles']) ?>)</h2>
            <?php if (empty($data['articles'])): ?>
                <p style="color: #999;">Nenhum artigo adicionado ainda.</p>
            <?php else: ?>
                <div class="articles-list">
                    <?php foreach (array_slice($data['articles'], 0, 20) as $article): ?>
                        <div class="article-item">
                            <div class="article-info">
                                <div class="article-title"><?= htmlspecialchars($article['title']) ?></div>
                                <div class="article-meta"><?= htmlspecialchars($article['source']) ?> • <?= date('d/m/Y', strtotime($article['date'] ?: 'now')) ?></div>
                            </div>
                            <div class="article-actions">
                                <button class="btn btn-secondary btn-small" onclick="editArticle('<?= htmlspecialchars($article['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($article['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($article['description'] ?? '', ENT_QUOTES) ?>')">Editar</button>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="delete_article" value="<?= htmlspecialchars($article['id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Tem certeza?')">Deletar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">✕</button>
            <h2 style="margin-bottom: 20px;">Editar Artigo</h2>
            <form onsubmit="saveArticle(event)">
                <input type="hidden" id="articleId">
                <div class="form-group">
                    <label for="editTitle">Título:</label>
                    <input type="text" id="editTitle" required>
                </div>
                <div class="form-group">
                    <label for="editDesc">Descrição:</label>
                    <textarea id="editDesc" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Salvar</button>
            </form>
        </div>
    </div>

    <script>
        function editArticle(id, title, desc) {
            document.getElementById('articleId').value = id;
            document.getElementById('editTitle').value = title;
            document.getElementById('editDesc').value = desc;
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function saveArticle(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('edit_article', '1');
            formData.append('article_id', document.getElementById('articleId').value);
            formData.append('title', document.getElementById('editTitle').value);
            formData.append('description', document.getElementById('editDesc').value);

            fetch('painel.php', {
                method: 'POST',
                body: formData
            }).then(() => {
                closeModal();
                location.reload();
            });
        }

        document.getElementById('editModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('editModal')) closeModal();
        });
    </script>

<?php endif; ?>

</body>
</html>
