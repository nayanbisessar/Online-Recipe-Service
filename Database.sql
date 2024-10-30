
drop database online_recipe_service;
CREATE DATABASE IF NOT EXISTS online_recipe_service;
USE online_recipe_service;

CREATE TABLE users (
  user_id INT PRIMARY KEY AUTO_INCREMENT,
  email VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  dietary_preferences VARCHAR(255),
  cooking_preferences VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE categories (
  category_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) UNIQUE NOT NULL
);


CREATE TABLE recipes (
  recipe_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  preparation_steps TEXT NOT NULL,
  cooking_time INT NOT NULL,
  servings INT NOT NULL,
  user_id INT NOT NULL,
  category_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (category_id) REFERENCES categories(category_id)
);


CREATE TABLE ingredients (
  ingredient_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL
);


CREATE TABLE recipe_ingredients (
  recipe_id INT,
  ingredient_id INT,
  quantity VARCHAR(255) NOT NULL,
  PRIMARY KEY (recipe_id, ingredient_id),
  FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id),
  FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id)
);



CREATE TABLE tags (
  tag_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) UNIQUE NOT NULL
);


CREATE TABLE recipe_tags (
  recipe_id INT,
  tag_id INT,
  PRIMARY KEY (recipe_id, tag_id),
  FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id),
  FOREIGN KEY (tag_id) REFERENCES tags(tag_id)
);

CREATE TABLE recipe_likes (
    recipe_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (recipe_id, user_id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE recipe_ratings (
    recipe_id INT NOT NULL,
    user_id INT NOT NULL,
    rating DECIMAL(2,1) NOT NULL CHECK (rating >= 1 AND rating <= 5),
    PRIMARY KEY (recipe_id, user_id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE saved_recipes (
    user_id INT NOT NULL,
    recipe_id INT NOT NULL,
    PRIMARY KEY (user_id, recipe_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id)
);

CREATE TABLE followers (
    follower_id INT,
    following_id INT,
    FOREIGN KEY (follower_id) REFERENCES users(user_id),
    FOREIGN KEY (following_id) REFERENCES users(user_id)
);




