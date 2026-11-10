<?php
require_once __DIR__ . '/../src/Services/AuthGuard.php';

AuthGuard::requireMaster();

$caminhoDer = __DIR__ . '/../docs/der.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modelo do Banco de Dados - FoodHub</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h1 {
            text-align: center;
            color: #333;
        }

        .modelo {
            text-align: center;
            margin-top: 25px;
        }

        .modelo img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .voltar {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 18px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="container">

        <h1>Modelo do Banco de Dados</h1>

        <p>
            Diagrama Entidade-Relacionamento (DER)
            do sistema FoodHub.
        </p>

        <div class="modelo">
            <?php if (file_exists($caminhoDer)): ?>

                <img
                    src="../docs/der.png"
                    alt="Diagrama do banco de dados FoodHub"
                >

            <?php else: ?>

                <p>Imagem do modelo do banco de dados não encontrada.</p>

            <?php endif; ?>
        </div>

        <a href="estoque.php" class="voltar">
            Voltar ao Controle de Estoque
        </a>

    </div>
</body>
</html>