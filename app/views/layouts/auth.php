<?php
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$basePath = rtrim(str_replace('/index.php', '', $scriptName), '/');
$asset = static function (string $path) use ($basePath): string {
    return ($basePath === '' ? '' : $basePath) . $path;
};
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= $csrfToken ?? '' ?>">

<title><?= $title ?? 'Connexion' ?> - Kombiphar</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Parisienne&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= htmlspecialchars($asset('/public/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Poppins',sans-serif;
}

body{
display:flex;
justify-content:center;
align-items:center;
min-height:100vh;
background:#f9f9f9;
}

.wrapper{
position:relative;
width:min(800px,95vw);
height:500px;
background:linear-gradient(90deg,#54880e,#f2f2f2);
border-radius:50px;
box-shadow:0 0 60px rgba(0,0,0,.3);
padding:60px;
display:flex;
align-items:center;
overflow:hidden;
}

.wrapper .text-right{
position:absolute;
top:60px;
right:180px;
color:#000;
text-shadow:0 0 20px rgba(0,0,0,.1);
font-size:50px;
font-family:'Parisienne',cursive;
}

.wrapper .text-right::before{
content:'Kombiphar';
position:absolute;
top:60px;
right:-50px;
color:#000;
text-shadow:0 0 20px rgba(0,0,0,.1);
}

.wrapper img{
position:absolute;
right:-40px;
bottom:-160px;
width:60%;
transform:rotate(260deg);
opacity:.35;
}

.form-wrapper{
z-index:2;
width:100%;
max-width:380px;
}

.form-wrapper h2{
font-size:2em;
text-align:center;
color:#000;
margin-bottom:20px;
}

.input-box{
position:relative;
width:100%;
margin:18px 0;
}

.input-box input{
width:100%;
height:50px;
background:transparent;
border:2px solid #f2f2f2;
outline:none;
border-radius:40px;
font-size:1em;
color:#f2f2f2;
padding:0 20px 0 45px;
}

.input-box input::placeholder{
color:rgba(255,255,255,.4);
}

.input-box .icon{
position:absolute;
left:15px;
top:50%;
transform:translateY(-50%);
color:#f2f2f2;
font-size:1.2em;
}

.forgot-pass{
margin-top:-10px;
margin-bottom:15px;
text-align:right;
}

.forgot-pass a{
    color:#000;
text-decoration:none;
}

.forgot-pass a:hover{
text-decoration:underline;
}

button{
width:100%;
height:50px;
background:#f2f2f2;
border:none;
border-radius:40px;
cursor:pointer;
font-size:1em;
color:#54880e;
font-weight:600;
}

button:hover{
background:#e8e8e8;
}

.sign-link{
font-size:.9em;
text-align:center;
margin-top:20px;
}

.sign-link p{
color:#000;
}

.sign-link a{
color:#000;
text-decoration:none;
font-weight:600;
}

.sign-link a:hover{
text-decoration:underline;
}

.auth-alert{
margin-bottom:16px;
padding:10px 12px;
font-size:13px;
border-radius:8px;
}

.auth-alert-error{
background:rgba(239,68,68,.12);
color:#b91c1c;
}

.auth-alert-success{
background:rgba(34,197,94,.12);
color:#15803d;
}

@media (max-width: 900px) {
    .wrapper {
        height: auto;
        padding: 40px 25px;
    }

    .wrapper .text-right {
        top: 40px;
        right: 25px;
        font-size: 38px;
    }

    .wrapper .text-right::before {
        top: 40px;
        right: -30px;
    }

    .wrapper img {
        right: -60px;
        bottom: -140px;
        width: 70%;
    }

    .form-wrapper {
        max-width: 360px;
    }
}

@media (max-width: 520px) {
    .wrapper {
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        padding: 30px 18px;
    }

    .wrapper .text-right {
        position: static;
        right: auto;
        top: auto;
        text-align: center;
        font-size: 32px;
        margin-bottom: 16px;
    }

    .wrapper .text-right::before {
        position: static;
        right: auto;
        top: auto;
        display: block;
        margin: 8px auto 18px;
        font-size: 28px;
        color: #000;
        text-shadow: none;
    }

    .wrapper img {
        right: -80px;
        bottom: -160px;
        width: 90%;
        opacity: 0.25;
    }

    .form-wrapper {
        width: 100%;
        max-width: 100%;
    }

    .form-wrapper h2 {
        font-size: 1.8em;
    }
}

</style>

</head>

<body>

<div class="wrapper <?= isset($wrapperClass) ? htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8') : '' ?>">

<!-- IMAGE DECORATIVE -->
<img src="<?= $asset('/public/images/login-shape.png') ?>" alt="Background">

<h2 class="text-right">Nous sommes</h2>

<?= $content ?>

<div class="auth-footer">
<?= $footer ?? '' ?>
</div>

</div>

<script src="<?= htmlspecialchars($asset('/public/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

</body>
</html>
