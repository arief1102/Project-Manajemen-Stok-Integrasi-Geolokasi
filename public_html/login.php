<?php


require_once 'auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_or_email = $_POST['username_or_email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username_or_email, $username_or_email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Generate a secure token
        $token = bin2hex(random_bytes(32));

        // Store the token in the database
        $stmt = $pdo->prepare("UPDATE users SET auth_token = ? WHERE user_id = ?");
        $stmt->execute([$token, $user['user_id']]);

        // Set a secure HTTP-only cookie
        $cookie_options = array(
            'expires' => time() + 86400 * 30, // 30 days
            'path' => '/',
            'domain' => 'jabonmekarplant.serv00.net', // your domain here
            'secure' => true, // set to true if using HTTPS
            'httponly' => true,
            'samesite' => 'Strict'
        );
        setcookie('auth_token', $token, $cookie_options);

        // Update last login
        $stmt = $pdo->prepare("INSERT INTO login (user_id) VALUES (?)");
        $stmt->execute([$user['user_id']]);

        // Redirect based on user role
        if ($user['role'] == 'admin') {
            header("Location: manage_customers.php");
        } elseif ($user['role'] == 'petani') {
            $stmt = $pdo->prepare("SELECT latitude, longitude FROM petani WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $petani = $stmt->fetch();

            if (!$petani || !$petani['latitude'] || !$petani['longitude']) {
                header("Location: update_location_petani.php");
            } else {
                header("Location: index.php");
            }
        } elseif ($user['role'] == 'pembeli') {
            header("Location: index.php");
        }
        exit();
    } else {
        $error = "Invalid username/email or password";

    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Login - Plant Inventory Jabon Mekar</title>
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/custom-styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.3.0/js/all.min.js"
        integrity="sha512-2bMhOkE/ACz21dJT8zBOMgMecNxx0d37NND803ExktKiKdSzdwn+L7i9fdccw/3V06gM/DBWKbYmQvKMdAA9Nw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
      @import url(https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap);.alert,.form-floating{margin-bottom:20px}.form-floating{position:relative}.input-icon,.password-toggle{position:absolute;top:50%;-webkit-transform:translateY(-50%);-ms-transform:translateY(-50%);transform:translateY(-50%)}.password-toggle{right:10px;background:0 0;border:none;cursor:pointer;font-size:1rem;color:#999}.password-toggle:focus{outline:0}.form-floating .password-toggle~input{padding-right:40px}.alert,.card-footer,.card-header{text-align:center}.logo,body{display:-webkit-box;display:-ms-flexbox;display:flex}*{margin:0;padding:0;-webkit-box-sizing:border-box;box-sizing:border-box}body{font-family:Poppins,sans-serif;background:-o-linear-gradient(315deg,#4caf50,#2196f3);background:linear-gradient(135deg,#4caf50,#2196f3);min-height:100vh;-webkit-box-align:center;-ms-flex-align:center;align-items:center;-webkit-box-pack:center;-ms-flex-pack:center;justify-content:center}.container{max-width:400px;width:90%;padding:20px}.card{background-color:rgba(255,255,255,.9);border-radius:20px;-webkit-box-shadow:0 10px 30px rgba(0,0,0,.1);box-shadow:0 10px 30px rgba(0,0,0,.1);overflow:hidden;-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);-webkit-transition:-webkit-transform .3s;transition:transform .3s;-o-transition:transform .3s;transition:transform .3s,-webkit-transform .3s}.btn-primary:hover,.logo{-webkit-box-shadow:0 5px 15px rgba(0,0,0,.1);box-shadow:0 5px 15px rgba(0,0,0,.1)}.card:hover{-webkit-transform:translateY(-5px);-ms-transform:translateY(-5px);transform:translateY(-5px)}.card-header{background-color:transparent;border-bottom:none;padding:30px 0 20px}.card-header h3{color:#333;font-weight:600;font-size:1.5rem;margin-bottom:5px}.card-header h5{color:#666;font-weight:400;font-size:1rem}.card-body{padding:30px}.form-floating input{border-radius:10px;border:1px solid #ddd;padding:15px;font-size:1rem;-webkit-transition:border-color .3s;-o-transition:border-color .3s;transition:border-color .3s}.form-floating input:focus{border-color:#4caf50;-webkit-box-shadow:0 0 0 .2rem rgba(76,175,80,.25);box-shadow:0 0 0 .2rem rgba(76,175,80,.25)}.form-floating label{padding:15px}.btn-primary{background-color:#4caf50;border:none;border-radius:10px;padding:12px;font-weight:600;font-size:1rem;-webkit-transition:.3s;-o-transition:.3s;transition:.3s;width:100%}.btn-primary:hover{background-color:#45a049;-webkit-transform:translateY(-2px);-ms-transform:translateY(-2px);transform:translateY(-2px)}.card-footer{background-color:transparent;border-top:none;padding:20px 30px}.card-footer a{color:#4caf50;text-decoration:none;font-weight:500;-webkit-transition:color .3s;-o-transition:color .3s;transition:color .3s}.card-footer a:hover{color:#45a049;text-decoration:underline}.alert{background-color:#f8d7da;color:#721c24;padding:10px;border-radius:5px}@media (max-width:576px){.container{width:100%;padding:10px}.card{border-radius:0}.card-body{padding:20px}}.logo{width:80px;height:80px;margin:0 auto 20px;background-color:#4caf50;border-radius:50%;-webkit-box-align:center;-ms-flex-align:center;align-items:center;-webkit-box-pack:center;-ms-flex-pack:center;justify-content:center}.logo i{font-size:40px;color:#fff}.form-floating input{padding-left:40px}.input-icon{left:15px;color:#999}@-webkit-keyframes float{0%,100%{-webkit-transform:translateY(0);transform:translateY(0)}50%{-webkit-transform:translateY(-10px);transform:translateY(-10px)}}@keyframes float{0%,100%{-webkit-transform:translateY(0);transform:translateY(0)}50%{-webkit-transform:translateY(-10px);transform:translateY(-10px)}}.floating{-webkit-animation:3s ease-in-out infinite float;animation:3s ease-in-out infinite float}
    </style>
    <script src="js/matomo.js"> </script>
</head>

<body>
    <div class="container">
        <div class="card shadow-lg">
            <div class="card-header">
                <div class="logo floating">
                    <i class="fas fa-leaf"></i>
                </div>
                <h4>Plant Inventory Jabon Mekar</h>
                    <h5>Login</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="post" autocomplete="on" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
                    <div class="form-floating mb-3">
                        <input class="form-control" id="inputUsernameEmail" type="text" name="username_or_email"
                            placeholder="Username or Email" required />
                        <label for="inputUsernameEmail">Username atau Email</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input class="form-control" id="inputPassword" type="password" name="password"
                            placeholder="Password" required />
                        <label for="inputPassword">Password</label>
                        <button type="button" class="password-toggle" data-target="inputPassword">👁️</button>
                    </div>
                    <div class="d-grid gap-2 mt-4">
                        <button class="btn btn-primary btn-lg" type="submit">Login</button>
                    </div>
                </form>
            </div>
            <div class="card-footer">
                <div class="small"><a href="register.php">Belum punya akun? Daftar disini!</a></div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"
        integrity="sha512-i9cEfJwUwViEPFKdC1enz4ZRGBj8YQo6QByFTF92YXHi7waCqyexvRD75S5NVTsSiTv7rKWqG9Y5eFxmRsOn0A=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="js/scripts.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () { let t = document.querySelector(".password-toggle"); t.addEventListener("click", function () { let t = this.getAttribute("data-target"), e = document.getElementById(t); "password" === e.type ? (e.type = "text", this.textContent = "\uD83D\uDD12") : (e.type = "password", this.textContent = "\uD83D\uDC41️") }) });
    </script>
</body>

</html>