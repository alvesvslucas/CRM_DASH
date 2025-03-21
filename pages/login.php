<?php include '../absoluto.php'; ?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Dash</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #ffffff;
    }

    .container {
      width: 1000px;
      height: 550px;
      display: flex;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
      border-radius: 10px;
      overflow: hidden;
    }

    .left {
      flex: 1;
      background: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 20px;
      position: relative;
    }

    .left h1 {
      font-size: 50px;
      font-weight: bold;
      color: #2c3e50;
      position: absolute;
      top: 30px;
      left: 50%;
      transform: translateX(-50%);
    }

    .left h1 span {
      color: red;
    }

    .right {
      flex: 1;
      background: #3b5998;
      color: white;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 40px;
    }

    .right h2 {
      font-size: 26px;
      font-weight: bold;
    }

    .form-control {
      margin-bottom: 15px;
    }

    .btn-login {
      background: #fff;
      color: #3b5998;
      font-weight: bold;
      width: 100%;
    }

    .forgot-link {
      color: #ffffff;
      text-decoration: none;
    }

    .left img {
      width: 90%;
      max-width: 420px;
      /* Ajustado para corresponder melhor ao modelo */
      height: auto;
      display: block;
      margin-top: 80px;
      transition: transform 0.3s ease-in-out;
      filter: drop-shadow(4px 4px 10px rgba(0, 0, 0, 0.2));
    }

    .left img:hover {
      transform: scale(1.05);
      filter: drop-shadow(6px 6px 15px rgba(0, 0, 0, 0.3));
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="left">
      <h1>Cred<span>ash</span></h1>
      <img src="<?php echo ASSET_PATH; ?>/icon1.png" alt="Illustration">
    </div>
    <div class="right">
      <h2>Bem vindo</h2>
      <p>Seu sistema de Dashboard</p>
      <form method="post" action="../backend/login_process.php">
        <input type="text" name="username" class="form-control" placeholder="Username" required>
        <input type="password" name="password" class="form-control" placeholder="Password" required>
        <div class="form-check">
          <input type="checkbox" name="remember_me" class="form-check-input" id="keep-logged">
          <label class="form-check-label" for="keep-logged">Mantenha-me conectado</label>
          <!-- <a type="hidden" href="#" class="forgot-link float-end">Esqueceu sua senha?</a> -->
        </div>
        <button type="submit" class="btn btn-login mt-3">Login</button>
      </form>

    </div>
  </div>
</body>

</html>