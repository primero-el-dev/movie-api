CREATE TABLE user (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    type VARCHAR(255) NOT NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE
);

-- Example user for testing purposes
INSERT INTO user (email, password, type) VALUES ('abc@mail.com', '$2a$12$lzzdmvvFq1juzlDUaUfW1OSma62ZlvlY0lKvmtjrtQ7qQLq2lzBCy', 'admin');

CREATE TABLE permission (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

INSERT INTO permission (name) VALUES('user_add'), ('user_delete');

CREATE TABLE user_permission (
    user_id INT(11) NOT NULL,
    permission_id INT(11) NOT NULL,
    blocked_to TIMESTAMP DEFAULT NOW(),
    CONSTRAINT FK__user_permission__user_id FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE,
    CONSTRAINT FK__user_permission__permission_id FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE
);

INSERT INTO user_permission (user_id, permission_id) VALUES (1, 1), (1, 2);

CREATE TABLE movie (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(511) NOT NULL,
    description TEXT DEFAULT NULL,
    length TIME DEFAULT NULL,
    created_at YEAR DEFAULT NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE genre (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    names VARCHAR(4095) DEFAULT '[]',
    description TEXT DEFAULT NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE movie_genre (
    movie_id INT(11) NOT NULL,
    genre_id INT(11) NOT NULL,
    CONSTRAINT FK__movie_genre__movie_id FOREIGN KEY (movie_id) REFERENCES movie (id) ON DELETE CASCADE,
    CONSTRAINT FK__movie_genre__genre_id FOREIGN KEY (genre_id) REFERENCES genre (id) ON DELETE CASCADE
);

CREATE TABLE country (
    id INT(3) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(255) DEFAULT NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT FK__country__flag FOREIGN KEY (flag) REFERENCES image (id) ON DELETE CASCADE
);

INSERT INTO country (name, code) VALUES ('Poland', 'PL');
INSERT INTO country (name, code) VALUES ('England', 'EN');
INSERT INTO country (name, code) VALUES ('Germany', 'DE');
INSERT INTO country (name, code) VALUES ('Spain', 'ES');
INSERT INTO country (name, code) VALUES ('Russia', 'RU');
INSERT INTO country (name, code) VALUES ('France', 'FR');

CREATE TABLE movie_country (
    movie_id INT(11) NOT NULL,
    country_id INT(3) NOT NULL,
    CONSTRAINT FK__movie_country__movie_id FOREIGN KEY (movie_id) REFERENCES movie(id) ON DELETE CASCADE,
    CONSTRAINT FK__movie_country__country_id FOREIGN KEY (country_id) REFERENCES country(id) ON DELETE CASCADE
);

CREATE TABLE person (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) DEFAULT NULL,
    surname VARCHAR(255) DEFAULT NULL,
    pseudo VARCHAR(255) DEFAULT NULL,
    country_id INT(3) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    birth DATE DEFAULT NULL,
    death DATE DEFAULT NULL,
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT FK__person__country_id FOREIGN KEY (country_id) REFERENCES country(id) ON DELETE CASCADE
);

CREATE TABLE person_movie_role (
    person_id INT(11) NOT NULL,
    movie_id INT(11) NOT NULL,
    role VARCHAR(255) NOT NULL,
    CONSTRAINT FK__person_movie_role__person_id FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE CASCADE,
    CONSTRAINT FK__person_movie_role__movie_id FOREIGN KEY (movie_id) REFERENCES movie (id) ON DELETE CASCADE
);
