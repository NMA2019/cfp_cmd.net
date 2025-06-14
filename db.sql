-- Création de la base de données
CREATE DATABASE IF NOT EXISTS cfpgesappv4 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE cfpgesappv4;

-- Table de contacts
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    email VARCHAR(60) NOT NULL,  
    sujet VARCHAR(60) NOT NULL, 
    message VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des rôles utilisateurs
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertion des rôles de base
INSERT INTO roles (name, description) VALUES 
('super_admin', 'Accès complet à toutes les fonctionnalités'),
('admin', 'Gestion complète sauf suppression définitive'),
('professeur', 'Gestion des étudiants et soutenances'),
('etudiant', 'Accès limité aux informations personnelles');

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    photo VARCHAR(255) DEFAULT 'default.png',
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    password_expires_at DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (token)
);

-- Table des filières
CREATE TABLE IF NOT EXISTS filieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    duration_months INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des types de formation
CREATE TABLE IF NOT EXISTS formation_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    tranches VARCHAR(50) NOT NULL COMMENT 'Format: nombre_tranches,pourcentage1,pourcentage2,...',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertion des types de formation
INSERT INTO formation_types (code, name, tranches) VALUES 
('AQP', 'AQP/TCF/TOFEL', '1,100'),
('CQP', 'CQP', '2,70,30'),
('DQP', 'DQP', '3,50,30,20');

-- Table des formations
CREATE TABLE IF NOT EXISTS formations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filiere_id INT NOT NULL,
    type_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    start_date DATE,
    end_date DATE,
    price DECIMAL(10,2) NOT NULL,
    capacity INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE RESTRICT,
    FOREIGN KEY (type_id) REFERENCES formation_types(id) ON DELETE RESTRICT
);

-- Table des modules
CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filiere_id INT NOT NULL,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    duration_hours INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE CASCADE
);

-- Table des étudiants
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    matricule VARCHAR(20) NOT NULL UNIQUE,
    date_of_birth DATE NOT NULL,
    gender ENUM('M', 'F', 'Autre'),
    cin VARCHAR(20) UNIQUE,
    photo VARCHAR(255) DEFAULT 'default.png',  -- Colonne photo ajoutée
    niveau_scolaire VARCHAR(50),
    formation_id INT NOT NULL,
    status ENUM('preinscrit', 'inscrit', 'formation', 'soutenance', 'diplome', 'abandon', 'suspendu') DEFAULT 'preinscrit',
    inscription_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE RESTRICT
);

-- Table de témoignages
CREATE TABLE IF NOT EXISTS temoignages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    students_id INT NOT NULL,
    approuve VARCHAR(255) NOT NULL, 
    photo_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (students_id) REFERENCES students(id) ON DELETE RESTRICT,
    FOREIGN KEY (photo_id) REFERENCES students(id) ON DELETE RESTRICT 
);

-- Table du personnel
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    type ENUM('administratif', 'enseignant', 'consultant', 'technique') NOT NULL,
    qualification VARCHAR(100),
    photo VARCHAR(255) DEFAULT 'default.png',  -- Colonne photo ajoutée
    hire_date DATE NOT NULL,
    salary DECIMAL(10,2),
    status ENUM('actif', 'inactif', 'congé') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Table des affectations enseignants-modules
CREATE TABLE IF NOT EXISTS teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    module_id INT NOT NULL,
    formation_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    hours_assigned INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE,
    UNIQUE (teacher_id, module_id, formation_id)
);

-- Table de la pension/écolage
CREATE TABLE IF NOT EXISTS pensions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    formation_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    remaining_amount DECIMAL(10,2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    status ENUM('non_paye', 'partiel', 'complet') DEFAULT 'non_paye',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE RESTRICT
);

-- Table des paiements
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pension_id INT NOT NULL,
    tranche_number INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('especes', 'cheque', 'virement', 'mobile_money') NOT NULL,
    reference VARCHAR(50) NOT NULL UNIQUE,
    received_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pension_id) REFERENCES pensions(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES staff(id) ON DELETE SET NULL
);

-- Table de la caisse
CREATE TABLE IF NOT EXISTS cash (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('entree', 'sortie') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_id INT,
    description VARCHAR(255) NOT NULL,
    reference VARCHAR(50) NOT NULL UNIQUE,
    handled_by INT NOT NULL,
    transaction_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    FOREIGN KEY (handled_by) REFERENCES staff(id) ON DELETE RESTRICT
);

-- Table des soutenances
CREATE TABLE IF NOT EXISTS soutenances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    formation_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    presentation_date DATETIME NOT NULL,
    teacher_id INT NOT NULL,
    co_teacher_id INT,
    room VARCHAR(50),
    status ENUM('planifiee', 'terminee', 'reportee', 'annulee') DEFAULT 'planifiee',
    note DECIMAL(4,2),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE RESTRICT,
    FOREIGN KEY (teacher_id) REFERENCES staff(id) ON DELETE RESTRICT,
    FOREIGN KEY (co_teacher_id) REFERENCES staff(id) ON DELETE SET NULL
);

-- Table de suivi des modules étudiants
CREATE TABLE IF NOT EXISTS student_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    module_id INT NOT NULL,
    status ENUM('non_commence', 'en_cours', 'valide', 'echec') DEFAULT 'non_commence',
    start_date DATE,
    end_date DATE,
    note DECIMAL(4,2),
    teacher_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES staff(id) ON DELETE SET NULL,
    UNIQUE (student_id, module_id)
);

-- Table des messages du chat
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT,
    group_id VARCHAR(50),
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des paramètres système
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table pour les pièces jointes du chat
CREATE TABLE IF NOT EXISTS chat_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Table pour les groupes de chat (optionnel)
CREATE TABLE IF NOT EXISTS chat_groups (
    group_id VARCHAR(50) PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS preinscription_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(255) NOT NULL,
    formation_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
);

CREATE INDEX idx_preinscription_ip ON preinscription_log(ip);
CREATE INDEX idx_preinscription_email ON preinscription_log(email);

-- Table pour les membres des groupes (optionnel)
CREATE TABLE IF NOT EXISTS group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES chat_groups(group_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (group_id, user_id)
);

-- Insertion des paramètres de base
INSERT INTO settings (setting_key, setting_value, description, is_public) VALUES 
('institution_name', 'CFP-CMD', 'Nom du centre de formation', TRUE),
('institution_slogan', 'Une formation de qualité pour un emploi sûr.', 'Slogan de l\'institution', TRUE),
('logo_url', 'assets/images/logo.png', 'URL du logo', TRUE),
('password_expiry_days', '90', 'Nombre de jours avant expiration du mot de passe', FALSE),
('default_currency', 'MGA', 'Devise par défaut', TRUE);

-- Création des vues utiles

-- Vue pour les étudiants avec leurs formations
CREATE VIEW student_details AS
SELECT 
    s.id, s.matricule, 
    CONCAT(u.first_name, ' ', u.last_name) AS full_name,
    u.email, u.phone, s.photo,  -- Utilisation de s.photo (photo de l'étudiant)
    s.date_of_birth, 
    TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age,
    s.gender, s.cin, s.niveau_scolaire,
    f.name AS formation, fi.name AS filiere, ft.name AS type_formation,
    s.status, s.inscription_date
FROM 
    students s
JOIN users u ON s.user_id = u.id
JOIN formations f ON s.formation_id = f.id
JOIN filieres fi ON f.filiere_id = fi.id
JOIN formation_types ft ON f.type_id = ft.id;

-- Vue pour les paiements et soldes
CREATE VIEW payment_balances AS
SELECT 
    p.id, p.student_id, s.matricule, 
    CONCAT(u.first_name, ' ', u.last_name) AS student_name,
    f.name AS formation, 
    p.total_amount, p.paid_amount, p.remaining_amount, p.status,
    COUNT(pm.id) AS payment_count,
    MAX(pm.payment_date) AS last_payment_date
FROM 
    pensions p
JOIN students s ON p.student_id = s.id
JOIN users u ON s.user_id = u.id
JOIN formations f ON p.formation_id = f.id
LEFT JOIN payments pm ON p.id = pm.pension_id
GROUP BY p.id;

-- Vue pour le calendrier des soutenances
CREATE VIEW soutenance_schedule AS
SELECT 
    so.id, so.presentation_date,
    s.id AS student_id, s.matricule,
    CONCAT(u.first_name, ' ', u.last_name) AS student_name,
    so.title, 
    st.id AS teacher_id, 
    CONCAT(ut.first_name, ' ', ut.last_name) AS teacher_name,
    st.photo AS teacher_photo,  -- Ajout de la photo du personnel
    so.room, so.status, so.note
FROM 
    soutenances so
JOIN students s ON so.student_id = s.id
JOIN users u ON s.user_id = u.id
JOIN staff st ON so.teacher_id = st.id
JOIN users ut ON st.user_id = ut.id;
