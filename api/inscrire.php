<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/db.php';

use Cloudinary\Cloudinary;

header('Content-Type: application/json; charset=utf-8');

function reponse(bool $success, string $message, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reponse(false, 'Méthode non autorisée.', 405);
}

// 1. Recevoir et vérifier les données
$nom    = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$ville  = trim($_POST['ville'] ?? '');

if ($nom === '' || $prenom === '' || $ville === '') {
    reponse(false, 'Tous les champs sont obligatoires.', 400);
}
if (mb_strlen($nom) > 100 || mb_strlen($prenom) > 100 || mb_strlen($ville) > 100) {
    reponse(false, 'Un champ dépasse 100 caractères.', 400);
}

// 2. Vérifier le fichier image
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    reponse(false, 'Photo manquante ou erreur de téléversement.', 400);
}
if ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
    reponse(false, 'La photo ne doit pas dépasser 2 Mo.', 400);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['photo']['tmp_name']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    reponse(false, 'Format accepté : JPG, PNG ou WebP.', 400);
}

try {
    // 3. Envoyer la photo à Cloudinary
    $cloudinary = new Cloudinary(getenv('CLOUDINARY_URL'));
    $result = $cloudinary->uploadApi()->upload(
        $_FILES['photo']['tmp_name'],
        ['folder' => 'stagiaires']
    );

    // 4. URL sécurisée
    $url = $result['secure_url'];

    // 5. Insertion dans MySQL
    $pdo = getDatabase();
    $stmt = $pdo->prepare(
        'INSERT INTO stagiaires (nom, prenom, ville, photo)
         VALUES (:nom, :prenom, :ville, :photo)'
    );
    $stmt->execute([
        ':nom' => $nom, ':prenom' => $prenom, ':ville' => $ville, ':photo' => $url,
    ]);

    // 6. Réponse JSON
    reponse(true, 'Stagiaire inscrit avec succès.', 201);
} catch (Throwable $e) {
    error_log($e->getMessage());
    reponse(false, "Erreur serveur : l'inscription a échoué.", 500);
}