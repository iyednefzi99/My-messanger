<?php
include __DIR__ . '/../src/auth.php';

// Deconnexion en POST uniquement : en GET, n'importe quelle balise <img>
// pointant ici deconnecterait l'utilisateur a son insu.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? '')) {
    header('Location: index.php');
    exit();
}

logout_user();
header('Location: login.php');
exit();
