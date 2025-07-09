<?php
include("./connect.php");

try{
    $sqlPost = "create table if not exists tbPost(
                id integer auto_increment primary key,
                title text,
                content text,
                image text,
                active integer,
                created_at timestamp,
                updated_at timestamp
                )";
    $pdo->query($sqlPost);

    $sqlUser =  "create table if not exists tbUser(
                id integer auto_increment primary key,
                username varchar(100),
                password text,
                role varchar(50),
                active integer,
                created_at timestamp,
                updated_at timestamp
                )";
    $pdo->query($sqlUser);
}catch(Exception $e){
    die("Failed: ".$e);
}


?>