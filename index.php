<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>IAA-cfms-login</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
        height: 100%;
        margin: 0;
        padding: 0;
        }
       
        body {
            font-family: 'Trebuchet MS', 'Lucida Sans Unicode', 'Lucida Grande', 'Lucida Sans', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }

        @keyframes fadeInSlideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-40px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .left-side {
            flex: 1;
            background: linear-gradient(135deg, #0b2b4b, #1a4d7a);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            padding: 2rem;
            animation: fadeInLeft 0.8s ease-out;
            position: relative;
            overflow: hidden;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.3);
            pointer-events: none;
            z-index: 0;
        }

        .circle-far {
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(2px);
        }

        .circle-dashed {
            border-style: dashed;
            border-width: 1px;
            background: transparent;
        }

        .circle-dotted {
            border-style: dotted;
            border-width: 2px;
        }

        .circle-1 { width: 400px; height: 400px; top: -150px; left: -150px; }
        .circle-2 { width: 600px; height: 600px; bottom: -200px; right: -200px; border-color: rgba(255,255,255,0.2); }
        .circle-3 { width: 200px; height: 200px; bottom: 20%; left: -80px; border-color: rgba(255,255,255,0.25); border-style: dotted; }
        .circle-4 { width: 150px; height: 150px; top: 20%; right: 10%; border-color: rgba(255,255,255,0.4); border-width: 1px; }
        .circle-5 { width: 80px; height: 80px; bottom: 15%; right: 15%; border-color: rgba(255,255,255,0.5); border-style: dashed; }
        .circle-6 { width: 300px; height: 300px; top: 50%; left: -100px; transform: translateY(-50%); border-color: rgba(255,255,255,0.1); background: rgba(255,255,255,0.02); }

        .left-side h1, .left-side p {
            position: relative;
            z-index: 1;
        }

        .left-side h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .left-side p {
            font-size: 1.2rem;
            max-width: 400px;
            line-height: 1.5;
        }

        .right-side {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f5f7fa;
            animation: fadeInSlideUp 0.8s ease-out;
        }

        .login-card {
            width: 100%;
            max-width: 380px;
            text-align: center;
            padding: 2rem;
        }

        .logo {
            width: 100px;
            height: auto;
            margin: 0 auto 1rem auto;
            display: block;
            animation: fadeInSlideUp 0.5s ease-out;
        }

        .login-card h2 {
            font-size: 1.8rem;
            color: #0b2b4b;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .input-container {
            position: relative;
            margin-bottom: 2rem;
            width: 100%;
        }

        .input-container i:not(.toggle-password) {
            position: absolute;
            left: 0;
            bottom: 10px;
            color: #1a4d7a;
            font-size: 1.1rem;
            transition: 0.2s;
            z-index: 1;
        }

        .input-container input {
            width: 100%;
            padding: 12px 0 6px 30px;
            border: none;
            border-bottom: 1px solid #ccc;
            font-size: 1rem;
            background: transparent;
            outline: none;
            transition: border-color 0.2s;
        }

        .input-container input:focus {
            border-bottom-color: #1a4d7a;
        }

        .input-container label {
            position: absolute;
            left: 30px;
            bottom: 6px;
            color: #aaa;
            font-size: 1rem;
            pointer-events: none;
            transition: 0.2s ease all;
        }

        .input-container input:focus ~ label,
        .input-container input:not(:placeholder-shown) ~ label {
            bottom: 28px;
            font-size: 0.75rem;
            color: #1a4d7a;
        }

        .input-container input::placeholder {
            opacity: 0;
        }

        .toggle-password {
            position: absolute;
            right: 0;
            bottom: 8px;
            color: #aaa;
            cursor: pointer;
            font-size: 1rem;
            transition: 0.2s;
            z-index: 2;
            display: none;
        }

        .toggle-password:hover {
            color: #1a4d7a;
        }

        .toggle-password.visible {
            display: block;
        }

        .forgot-link {
            display: block;
            text-align: right;
            margin: 0.5rem 0 1.5rem 0;
            font-size: 0.85rem;
            color: #1a4d7a;
            text-decoration: none;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .submit-btn {
            width: 100%;
            background: #0b2b4b;
            color: white;
            border: none;
            padding: 12px;
            font-size: 1rem;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s, transform 0.1s;
        }

        .submit-btn:hover {
            background: #1a4d7a;
        }

        .submit-btn:active {
            transform: scale(0.98);
        }

        /* Simple error text - only red color, no background, no box */
        .error-text {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 15px;
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            .left-side {
                padding: 3rem 1rem;
            }
            .left-side h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="left-side">
        <div class="circle circle-1 circle-far"></div>
        <div class="circle circle-2 circle-far"></div>
        <div class="circle circle-3 circle-dotted"></div>
        <div class="circle circle-4"></div>
        <div class="circle circle-5 circle-dashed"></div>
        <div class="circle circle-6 circle-far"></div>

        <h1>Welcome to IAA</h1>
        <p>Institute of Accountancy Arusha - Complaint Management System</p>
        <p style="margin-top: 1rem;">Your voice matters. Submit and track complaints easily.</p>
    </div>

    <div class="right-side">
        <div class="login-card">
            <img src="images/logo.png" alt="IAA Logo" class="logo">
            <h2>Login</h2>

            <!-- Simple text error message (no box, just text) -->
            <?php if (isset($_SESSION['login_error'])): ?>
                <div class="error-text">
                    <?php echo htmlspecialchars($_SESSION['login_error']); unset($_SESSION['login_error']); ?>
                </div>
            <?php endif; ?>

            <form action="login_process.php" method="POST">
                <div class="input-container">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" placeholder=" " required>
                    <label for="username">Username</label>
                </div>
                <div class="input-container">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder=" " required>
                    <label for="password">Password</label>
                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                </div>
                <a href="#" class="forgot-link">Forgot password?</a>
                <button type="submit" class="submit-btn">Sign In</button>
            </form>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        passwordInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                togglePassword.classList.add('visible');
            } else {
                togglePassword.classList.remove('visible');
            }
        });

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>