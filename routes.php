<?php

require_once __DIR__.'/router.php';
require_once 'config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Vérifier si l'utilisateur est connecté
$isLoggedIn = isset($_SESSION['user_id']);
$profilePhoto = $isLoggedIn ? ($_SESSION['photo_profil'] ?? 'default_photo_path.jpg') : '';


// Helper function to send JSON response
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}

// Route pour déconnexion
get('/deconnexion', function() {
  
    session_unset();
    session_destroy();
    header('Location: PageConnexion.php');
    exit();
});

post('/api/signup', function() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse(['message' => 'Invalid JSON format']);
        return;
    }

    $pdo = getConnection();
    $nom = $input['name'];
    $prenom = $input['prenom'];
    $email = $input['email'];
    $password = password_hash($input['password'], PASSWORD_BCRYPT);
    $phone = $input['phone'];
    $birthdate = $input['birthdate'];
    $license = $input['license'];

    $stmt = $pdo->prepare("INSERT INTO Utilisateurs (nom, prenom, email, mot_de_passe_hash, numero_telephone, date_naissance, numero_permis_conduire, role) VALUES (:nom, :prenom, :email, :mot_de_passe_hash, :numero_telephone, :date_naissance, :numero_permis_conduire, 'client')");
    
    if ($stmt->execute([
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => $email,
        'mot_de_passe_hash' => $password,
        'numero_telephone' => $phone,
        'date_naissance' => $birthdate,
        'numero_permis_conduire' => $license
    ])) {
        sendJsonResponse(['message' => 'Inscription réussie']);
    } else {
        sendJsonResponse(['message' => 'Erreur lors de l\'inscription']);
    }
});

function sendJsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
post('/api/login', function() {
    $input = json_decode(file_get_contents('php://input'), true);
    $pdo = getConnection();

    $email = $input['email'];
    $password = $input['password'];

    $stmt = $pdo->prepare("SELECT * FROM Utilisateurs WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['mot_de_passe_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        sendJsonResponse(['message' => 'Connexion réussie', 'user' => $user]);
    } else {
        sendJsonResponse(['message' => 'Email ou mot de passe incorrect']);
    }
});

post('/api/registerVehicle', function() {
    $input = json_decode(file_get_contents('php://input'), true);
    $pdo = getConnection();

    // Log the received data for debugging
    error_log('Received data: ' . print_r($input, true));

    // Validate that all expected fields are present
    $requiredFields = ['user_id', 'address', 'marque','model', 'year', 'vin', 'mileage', 'transmission', 'licensePlate', 'description', 'advanceNotice', 'minTripDuration', 'maxTripDuration', 'dailyPrice', 'acceptStandards', 'photos'];

    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            echo json_encode(['message' => "Field '$field' is required"]);
            return;
        }
    }

    $userId = $input['user_id'];

    $stmt = $pdo->prepare("SELECT role FROM Utilisateurs WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['message' => 'User not found']);
        return;
    }

    if ($user['role'] !== 'proprietaire') {
        $stmt = $pdo->prepare("UPDATE Utilisateurs SET role = 'proprietaire' WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
    }

    $adresse = $input['address'];
    $marque = $input['marque'];
    $modele = $input['model'];
    $annee = $input['year'];
    $vin = $input['vin'];
    $kilometrage = $input['mileage'];
    $transmission = $input['transmission'];
    $finition = $input['finition'];
    $style = $input['style'];
    $taxes = $input['taxes'] === 'Oui' ? 1 : 0;
    $noSalvage = $input['no_salvage'] ? 1 : 0;
    $plaqueImmatriculation = $input['licensePlate'];
    $caracteristiques = isset($input['features']) ? implode(',', $input['features']) : '';
    $description = $input['description'];
    $preavis = $input['advanceNotice'];
    $dureeMinimum = $input['minTripDuration'];
    $dureeMaximum = $input['maxTripDuration'];
    $prixQuotidien = $input['dailyPrice'];
    $normesAcceptees = $input['acceptStandards'] ? 1 : 0;
    $photos = json_encode($input['photos']); // JSON string of photos

    $stmt = $pdo->prepare("INSERT INTO Vehicules (
        user_id, adresse, marque, modele, annee, vin, kilometrage, transmission, finition, style, taxes_payees, no_salvage, 
        plaque_immatriculation, caracteristiques, description, preavis, duree_minimum, duree_maximum, 
        prix_quotidien, normes_acceptees, photos
    ) VALUES (
        :user_id, :adresse, :marque, :modele, :annee, :vin, :kilometrage, :transmission, :finition, :style, :taxes_payees, :no_salvage, 
        :plaque_immatriculation, :caracteristiques, :description, :preavis, :duree_minimum, :duree_maximum, 
        :prix_quotidien, :normes_acceptees, :photos
    )");

    try {
        if ($stmt->execute([
            'user_id' => $userId,
            'adresse' => $adresse,
            'marque' => $marque,
            'modele' => $modele,
            'annee' => $annee,
            'vin' => $vin,
            'kilometrage' => $kilometrage,
            'transmission' => $transmission,
            'finition' => $finition,
            'style' => $style,
            'taxes_payees' => $taxes,
            'no_salvage' => $noSalvage,
            'plaque_immatriculation' => $plaqueImmatriculation,
            'caracteristiques' => $caracteristiques,
            'description' => $description,
            'preavis' => $preavis,
            'duree_minimum' => $dureeMinimum,
            'duree_maximum' => $dureeMaximum,
            'prix_quotidien' => $prixQuotidien,
            'normes_acceptees' => $normesAcceptees,
            'photos' => $photos
        ])) {
            echo json_encode(['message' => 'Véhicule enregistré avec succès']);
        } else {
            error_log('SQL execution error: ' . print_r($stmt->errorInfo(), true));
            echo json_encode(['message' => 'Erreur lors de l\'enregistrement du véhicule']);
        }
    } catch (PDOException $e) {
        error_log('PDOException: ' . $e->getMessage());
        echo json_encode(['message' => 'Erreur lors de l\'enregistrement du véhicule: ' . $e->getMessage()]);
    }
});

post('/api/uploadPhotos', function() {
    // Create an array to hold the paths of uploaded files
    $filePaths = [];

    // Directory where the photos will be stored
    $targetDir = "uploads/";

    // Ensure the directory exists
    if (!file_exists($targetDir) && !mkdir($targetDir, 0777, true)) {
        sendJsonResponse(['message' => 'Erreur lors de la création du répertoire de téléchargement']);
    }

    foreach ($_FILES['photos']['name'] as $key => $name) {
        $targetFile = $targetDir . basename($name);

        // Move the uploaded file to the target directory
        if (move_uploaded_file($_FILES['photos']['tmp_name'][$key], $targetFile)) {
            $filePaths[] = $targetFile;
        } else {
            sendJsonResponse(['message' => 'Erreur lors du téléchargement des photos']);
        }
    }

    sendJsonResponse(['filePaths' => $filePaths]);
});

get('/api/getVehicles', function() {
    header('Content-Type: application/json');
    $pdo = getConnection();

    $stmt = $pdo->prepare("SELECT * FROM Vehicules");
    $stmt->execute();
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($vehicles);
});

post('/api/uploadProfilePhoto', function() {
    require_once 'config.php';

    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_FILES['profile_photo'])) {
            echo json_encode(['message' => 'No file uploaded']);
            exit();
        }

        $pdo = getConnection();

        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Get the current user's ID from the session
        $userId = $_SESSION['user_id'];

        // Fetch the current photo path from the database
        $stmt = $pdo->prepare("SELECT photo_profil FROM Utilisateurs WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['message' => 'User not found']);
            exit();
        }

        $currentPhotoPath = $user['photo_profil'];

        // Directory where the photos will be stored
        $targetDir = "uploads/";

        // Ensure the directory exists
        if (!file_exists($targetDir) && !mkdir($targetDir, 0777, true)) {
            echo json_encode(['message' => 'Failed to create upload directory']);
            exit();
        }

        $targetFile = $targetDir . basename($_FILES['profile_photo']['name']);

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetFile)) {
            // Delete the old photo if it exists
            if ($currentPhotoPath && file_exists($currentPhotoPath)) {
                unlink($currentPhotoPath);
            }

            // Update the database with the new photo path
            $stmt = $pdo->prepare("UPDATE Utilisateurs SET photo_profil = :photo_profil WHERE user_id = :user_id");
            $stmt->execute(['photo_profil' => $targetFile, 'user_id' => $userId]);

            echo json_encode(['message' => 'Photo uploaded successfully', 'filePath' => $targetFile]);
        } else {
            echo json_encode(['message' => 'Failed to upload photo']);
        }
    }
});



post('/api/updateProfile', function() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid JSON input']);
        exit();
    }

    if (!isset($input['user_id']) || !isset($input['nom']) || !isset($input['prenom']) || !isset($input['email']) || !isset($input['date_naissance']) || !isset($input['numero_permis_conduire']) || !isset($input['numero_telephone'])) {
        sendJsonResponse(['success' => false, 'message' => 'Tous les champs requis ne sont pas présents.']);
        exit();
    }

    $userId = $input['user_id'];
    $nom = $input['nom'];
    $prenom = $input['prenom'];
    $email = $input['email'];
    $date_naissance = $input['date_naissance'];
    $numero_permis_conduire = $input['numero_permis_conduire'];
    $numero_telephone = $input['numero_telephone'];

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("UPDATE Utilisateurs SET nom = :nom, prenom = :prenom, email = :email, date_naissance = :date_naissance, numero_permis_conduire = :numero_permis_conduire, numero_telephone = :numero_telephone WHERE user_id = :user_id");

        $success = $stmt->execute([
            'user_id' => $userId,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'date_naissance' => $date_naissance,
            'numero_permis_conduire' => $numero_permis_conduire,
            'numero_telephone' => $numero_telephone
        ]);

        if ($success) {
            sendJsonResponse(['success' => true, 'message' => 'Profil mis à jour avec succès.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Erreur lors de la mise à jour du profil.']);
        }
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()]);
    }
});


post('/api/changePassword', function() {
    require_once 'config.php';

    header('Content-Type: application/json');

    // session_start() is not necessary because it's already started earlier in this script

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit();
    }

    if (!isset($input['user_id']) || !isset($input['current_password']) || !isset($input['new_password']) || !isset($input['confirm_password'])) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs requis ne sont pas présents.']);
        exit();
    }

    if ($input['new_password'] !== $input['confirm_password']) {
        echo json_encode(['success' => false, 'message' => 'Les nouveaux mots de passe ne correspondent pas.']);
        exit();
    }

    $userId = $input['user_id'];
    $currentPassword = $input['current_password'];
    $newPassword = $input['new_password'];

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT mot_de_passe_hash FROM Utilisateurs WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($currentPassword, $user['mot_de_passe_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Mot de passe actuel incorrect.']);
            exit();
        }

        $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE Utilisateurs SET mot_de_passe_hash = :new_password_hash WHERE user_id = :user_id");
        $stmt->execute(['new_password_hash' => $newPasswordHash, 'user_id' => $userId]);

        echo json_encode(['success' => true, 'message' => 'Mot de passe mis à jour avec succès.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()]);
    }
});

post('/api/enregistrerReservation', function() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid JSON input']);
        exit();
    }

    if (!isset($input['user_id']) || !isset($input['vehicule_id']) || !isset($input['start_date']) || !isset($input['end_date'])) {
        sendJsonResponse(['success' => false, 'message' => 'Tous les champs requis ne sont pas présents.']);
        exit();
    }

    $userId = $input['user_id'];
    $vehiculeId = $input['vehicule_id'];
    $startDate = $input['start_date'];
    $endDate = $input['end_date'];
    $cancelDate = isset($input['cancel_date']) ? $input['cancel_date'] : null;

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("INSERT INTO reservations (user_id, vehicule_id, start_date, end_date, cancel_date) VALUES (:user_id, :vehicule_id, :start_date, :end_date, :cancel_date)");

        $success = $stmt->execute([
            'user_id' => $userId,
            'vehicule_id' => $vehiculeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cancel_date' => $cancelDate
        ]);

        if ($success) {
            sendJsonResponse(['success' => true, 'message' => 'Réservation enregistrée avec succès.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de la réservation.']);
        }
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()]);
    }
});


post('/api/createConversation', function() {
    $input = json_decode(file_get_contents('php://input'), true);
    $pdo = getConnection();

    $user1_id = $input['user1_id'];
    $user2_id = $input['user2_id'];
    $vehicle_id = $input['vehicle_id'];

    $stmt = $pdo->prepare("INSERT INTO conversations (user1_id, user2_id, vehicle_id) VALUES (:user1_id, :user2_id, :vehicle_id)");
    if ($stmt->execute(['user1_id' => $user1_id, 'user2_id' => $user2_id, 'vehicle_id' => $vehicle_id])) {
        sendJsonResponse(['message' => 'Conversation created successfully', 'conversation_id' => $pdo->lastInsertId()]);
    } else {
        sendJsonResponse(['message' => 'Error creating conversation']);
    }
});

post('/api/sendMessage', function() {
    $input = json_decode(file_get_contents('php://input'), true);
    $pdo = getConnection();

    $conversation_id = $input['conversation_id'];
    $sender_id = $input['sender_id'];
    $message = $input['message'];

    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message) VALUES (:conversation_id, :sender_id, :message)");
    if ($stmt->execute(['conversation_id' => $conversation_id, 'sender_id' => $sender_id, 'message' => $message])) {
        sendJsonResponse(['message' => 'Message sent successfully']);
    } else {
        sendJsonResponse(['message' => 'Error sending message']);
    }
});

get('/api/getMessages', function() {
    $conversation_id = $_GET['conversation_id'];
    $pdo = getConnection();

    $stmt = $pdo->prepare("SELECT * FROM messages WHERE conversation_id = :conversation_id ORDER BY timestamp ASC");
    $stmt->execute(['conversation_id' => $conversation_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJsonResponse($messages);
});

post('/api/updateVehicleDetails', function() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid JSON input']);
        exit();
    }

    // Vérifiez que les champs requis sont présents
    if (!isset($input['vehicule_id']) || !isset($input['plaque_immatriculation']) || !isset($input['caracteristiques']) || !isset($input['description'])) {
        sendJsonResponse(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    $vehiculeId = $input['vehicule_id'];
    $plaqueImmatriculation = $input['plaque_immatriculation'];
    $caracteristiques = $input['caracteristiques'];
    $description = $input['description'];

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            UPDATE Vehicules
            SET plaque_immatriculation = :plaque_immatriculation,
                caracteristiques = :caracteristiques,
                description = :description,
                date_mise_a_jour = CURRENT_TIMESTAMP
            WHERE vehicule_id = :vehicule_id
        ");

        $success = $stmt->execute([
            'plaque_immatriculation' => $plaqueImmatriculation,
            'caracteristiques' => $caracteristiques,
            'description' => $description,
            'vehicule_id' => $vehiculeId
        ]);

        if ($success) {
            sendJsonResponse(['success' => true, 'message' => 'Véhicule mis à jour avec succès.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Erreur lors de la mise à jour du véhicule.']);
        }
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()]);
    }
});


post('/api/changerDisponibilite', function() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid JSON input']);
        exit();
    }

    // Vérifiez que les champs requis sont présents
    if (!isset($input['vehicule_id']) || !isset($input['disponibilite'])) {
        sendJsonResponse(['success' => false, 'message' => 'vehicule_id and disponibilite fields are required']);
        exit();
    }

    $vehiculeId = $input['vehicule_id'];
    $disponibilite = $input['disponibilite'];

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            UPDATE Vehicules
            SET disponibilite = :disponibilite,
                date_mise_a_jour = CURRENT_TIMESTAMP
            WHERE vehicule_id = :vehicule_id
        ");

        $success = $stmt->execute([
            'disponibilite' => $disponibilite,
            'vehicule_id' => $vehiculeId
        ]);

        if ($success) {
            sendJsonResponse(['success' => true, 'message' => 'Disponibilité du véhicule mise à jour avec succès.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Erreur lors de la mise à jour de la disponibilité du véhicule.']);
        }
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()]);
    }
});


post('/api/updateVehicleAvailability', function() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid JSON input']);
        exit();
    }

    // Vérifiez que les champs requis sont présents
    if (!isset($input['vehicule_id']) || !isset($input['advanceNotice']) || !isset($input['minTripDuration']) || !isset($input['maxTripDuration'])) {
        sendJsonResponse(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    $vehiculeId = $input['vehicule_id'];
    $advanceNotice = $input['advanceNotice'];
    $minTripDuration = $input['minTripDuration'];
    $maxTripDuration = $input['maxTripDuration'];

    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            UPDATE Vehicules
            SET preavis = :advanceNotice,
                duree_minimum = :minTripDuration,
                duree_maximum = :maxTripDuration,
                date_mise_a_jour = CURRENT_TIMESTAMP
            WHERE vehicule_id = :vehicule_id
        ");

        $success = $stmt->execute([
            'advanceNotice' => $advanceNotice,
            'minTripDuration' => $minTripDuration,
            'maxTripDuration' => $maxTripDuration,
            'vehicule_id' => $vehiculeId
        ]);

        if ($success) {
            sendJsonResponse(['success' => true, 'message' => 'Disponibilité du véhicule mise à jour avec succès.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Erreur lors de la mise à jour de la disponibilité du véhicule.']);
        }
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()]);
    }
});



post('/api/updateVehiclePhotos', function() {
    // Handle the uploaded files
    if (!empty($_FILES['newPhotos'])) {
        $uploadedPhotos = [];

        foreach ($_FILES['newPhotos']['tmp_name'] as $index => $tmpName) {
            if ($_FILES['newPhotos']['error'][$index] === UPLOAD_ERR_OK) {
                $uploadDir = '/path/to/upload/directory/';
                $fileName = basename($_FILES['newPhotos']['name'][$index]);
                $filePath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $filePath)) {
                    $uploadedPhotos[] = $filePath;
                } else {
                    sendJsonResponse(['success' => false, 'message' => 'Error uploading photos.']);
                    exit();
                }
            }
        }

        // Update the database with new photos and removed photos
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("
                UPDATE Vehicules
                SET photos = :photos,
                    date_mise_a_jour = CURRENT_TIMESTAMP
                WHERE vehicule_id = :vehicule_id
            ");

            $allPhotos = array_merge($uploadedPhotos, json_decode($input['removedPhotos']));  // Combine new and existing photos
            $success = $stmt->execute([
                'photos' => json_encode($allPhotos),
                'vehicule_id' => $input['vehicule_id']
            ]);

            if ($success) {
                sendJsonResponse(['success' => true, 'message' => 'Photos mises à jour avec succès.']);
            } else {
                sendJsonResponse(['success' => false, 'message' => 'Erreur lors de la mise à jour des photos.']);
            }
        } catch (PDOException $e) {
            sendJsonResponse(['success' => false, 'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()]);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'No photos uploaded.']);
    }
});


get('/api/getVehicules', function() {
    $pdo = getConnection();

    // Récupérer les paramètres d'entrée
    $adresse = isset($_GET['adresse']) ? $_GET['adresse'] : null;
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

    if (!$adresse || !$start_date || !$end_date) {
        sendJsonResponse(['success' => false, 'message' => 'Les paramètres adresse, start_date, et end_date sont requis.']);
        return;
    }

    try {
        // Sélectionner les véhicules disponibles à l'adresse donnée et qui ne sont pas réservés pendant la période spécifiée
        $stmt = $pdo->prepare("
            SELECT v.*
            FROM Vehicules v
            WHERE v.adresse LIKE :adresse
            AND v.disponibilite = 1
            AND NOT EXISTS (
                SELECT 1 
                FROM reservations r 
                WHERE r.vehicule_id = v.vehicule_id
                AND (
                    (r.start_date <= :end_date AND r.end_date >= :start_date)
                )
            )
        ");

        $stmt->execute([
            'adresse' => '%' . $adresse . '%',
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);

        $vehicules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJsonResponse(['success' => true, 'vehicules' => $vehicules]);
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Erreur de connexion à la base de données : ' . $e->getMessage()]);
    }
});






// chemin routes

get('/', function() use ($isLoggedIn, $profilePhoto) {
    include 'Accueil.php';
});
get('/index.php', function() use ($isLoggedIn, $profilePhoto) {
    include 'index.php';
});
get('/', 'Accueil.php');
get('/Accueil.php', 'Accueil.php');
get('/index.php', 'index.php');
get('/PageConnexion.php', 'PageConnexion.php');
get('/Location.php', 'location.php');
get('/EnregistrerVehicule.php', 'EnregistrerVehicule.php');
get('/VoitureDetail.php', 'voitureDetail.php');
get('/Hreservation.php', 'Hreservation.php');
get('/MonCompte.php', 'MonCompte.php');
get('/MonVehicule.php', 'MonVehicule.php');
get('/chat', 'chat.php');
get('/fetchMessages.php', 'fetchMessages.php');
get('/ModifierVehicule.php', 'ModifierVehicule.php');
get('/Paiement.php', 'Paiement.php');
any('/404','views/404.php');





// POST routes
post('/user', '/api/save_user');
any('/404','views/404.php');
post('/api/uploadPhotos', 'uploadPhotos.php');
?>
