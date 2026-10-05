CREATE DATABASE IF NOT EXISTS parc_des_prince;
USE parc_des_prince;


CREATE TABLE CATEGORIE (
    id_categorie INT AUTO_INCREMENT PRIMARY KEY,
    nom_categorie VARCHAR(100) NOT NULL,
    type_categorie VARCHAR(50)
);


CREATE TABLE UTILISATEUR (
    email VARCHAR(100) PRIMARY KEY,
    mot_passe VARCHAR(255) NOT NULL, 
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL,
    telephone VARCHAR(20),
    role VARCHAR(20) NOT NULL,
    email_admin VARCHAR(100),
    FOREIGN KEY (email_admin) REFERENCES UTILISATEUR(email) ON DELETE SET NULL
);


CREATE TABLE ADMIN (
    email VARCHAR(100) PRIMARY KEY,
    FOREIGN KEY (email) REFERENCES UTILISATEUR(email) ON DELETE CASCADE
);

CREATE TABLE CLIENT (
    email VARCHAR(100) PRIMARY KEY,
    date_inscription DATE NOT NULL,
    FOREIGN KEY (email) REFERENCES UTILISATEUR(email) ON DELETE CASCADE
);

CREATE TABLE AGENT_DE_VENTE (
    email VARCHAR(100) PRIMARY KEY,
    FOREIGN KEY (email) REFERENCES UTILISATEUR(email) ON DELETE CASCADE
);

CREATE TABLE RESPONSABLE_DE_JEU (
    email VARCHAR(100) PRIMARY KEY,
    fonction VARCHAR(100),
    date_embauche DATE,
    statut_responsable VARCHAR(50),
    FOREIGN KEY (email) REFERENCES UTILISATEUR(email) ON DELETE CASCADE
);


CREATE TABLE JEU (
    id_jeu INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    age_min INT,
    capacite INT,
    duree_session INT, -- بالدقائق مثلا
    tarif DECIMAL(10,2) NOT NULL,
    etat VARCHAR(50) NOT NULL, -- مثال: disponible, en maintenance...
    date_mise_service DATE,
    id_categorie INT NOT NULL,
    email_responsable VARCHAR(100),
    email_admin VARCHAR(100),
    FOREIGN KEY (id_categorie) REFERENCES CATEGORIE(id_categorie),
    FOREIGN KEY (email_responsable) REFERENCES RESPONSABLE_DE_JEU(email) ON DELETE SET NULL,
    FOREIGN KEY (email_admin) REFERENCES ADMIN(email) ON DELETE SET NULL
);


CREATE TABLE MAINTENANCE (
    id_maintenance INT AUTO_INCREMENT PRIMARY KEY,
    type_intervention VARCHAR(100) NOT NULL,
    description TEXT,
    date_intervention DATE NOT NULL,
    cout DECIMAL(10,2),
    etat_intervention VARCHAR(50),
    date_prochaine_maintenance DATE,
    id_jeu INT NOT NULL,
    email_responsable VARCHAR(100),
    FOREIGN KEY (id_jeu) REFERENCES JEU(id_jeu) ON DELETE CASCADE,
    FOREIGN KEY (email_responsable) REFERENCES RESPONSABLE_DE_JEU(email) ON DELETE SET NULL
);

CREATE TABLE DEPENSE (
    id_depense INT AUTO_INCREMENT PRIMARY KEY,
    categorie_depense VARCHAR(100),
    description_depense TEXT,
    montant_depense DECIMAL(10,2) NOT NULL,
    date_depense DATE NOT NULL,
    type_depense VARCHAR(50),
    fichier_justificatif VARCHAR(255),
    email_utilisateur VARCHAR(100),
    id_jeu INT,
    FOREIGN KEY (email_utilisateur) REFERENCES UTILISATEUR(email) ON DELETE SET NULL,
    FOREIGN KEY (id_jeu) REFERENCES JEU(id_jeu) ON DELETE CASCADE
);


CREATE TABLE RESERVATION (
    num_reservation INT AUTO_INCREMENT PRIMARY KEY,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_visite DATETIME NOT NULL,
    nb_personnes INT NOT NULL,
    montant DECIMAL(10,2) NOT NULL,
    statut_reservation VARCHAR(50) NOT NULL,
    email_client VARCHAR(100) NOT NULL,
    email_agent VARCHAR(100),
    FOREIGN KEY (email_client) REFERENCES CLIENT(email) ON DELETE CASCADE,
    FOREIGN KEY (email_agent) REFERENCES AGENT_DE_VENTE(email) ON DELETE SET NULL
);

CREATE TABLE choisir(
    num_reservation INT,
    id_jeu INT,
    PRIMARY KEY (num_reservation, id_jeu),
    FOREIGN KEY (num_reservation) REFERENCES RESERVATION(num_reservation) ON DELETE CASCADE,
    FOREIGN KEY (id_jeu) REFERENCES JEU(id_jeu) ON DELETE CASCADE
);

CREATE TABLE COMMANDE (
    num_commande INT AUTO_INCREMENT PRIMARY KEY,
    date_commande DATETIME DEFAULT CURRENT_TIMESTAMP,
    montant_total DECIMAL(10,2) NOT NULL,
    statut_commande VARCHAR(50) NOT NULL,
    mode_paiement VARCHAR(50),
    email_client VARCHAR(100) NOT NULL,
    email_agent VARCHAR(100),
    FOREIGN KEY (email_client) REFERENCES CLIENT(email) ON DELETE CASCADE,
    FOREIGN KEY (email_agent) REFERENCES AGENT_DE_VENTE(email) ON DELETE SET NULL
);


CREATE TABLE BILLET (
    id_billet INT AUTO_INCREMENT PRIMARY KEY,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_fin DATETIME,
    type VARCHAR(50) NOT NULL, -- مثال: enfant, adulte, pass journée
    statut_billet VARCHAR(50) NOT NULL,
    quantite INT NOT NULL DEFAULT 1,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    num_commande INT NOT NULL,
    email_agent VARCHAR(100),
    FOREIGN KEY (num_commande) REFERENCES COMMANDE(num_commande) ON DELETE CASCADE,
    FOREIGN KEY (email_agent) REFERENCES AGENT_DE_VENTE(email) ON DELETE SET NULL
);

CREATE TABLE contient (
    id_billet INT,
    id_jeu INT,
    PRIMARY KEY (id_billet, id_jeu),
    FOREIGN KEY (id_billet) REFERENCES BILLET(id_billet) ON DELETE CASCADE,
    FOREIGN KEY (id_jeu) REFERENCES JEU(id_jeu) ON DELETE CASCADE
);

