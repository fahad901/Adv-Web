<?php
session_start();
const DB_HOST='localhost'; const DB_NAME='neoarcade'; const DB_USER='root'; const DB_PASS='';
function db(): PDO {
 static $pdo=null;
 if (!$pdo) {
  $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 }
 return $pdo;
}
function e($v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function user(): ?array {return $_SESSION['user']??null;}
function csrf(): string {return $_SESSION['csrf']??=bin2hex(random_bytes(24));}
function verify_csrf(): void {if(!hash_equals(csrf(),$_POST['csrf']??'')){http_response_code(403);exit('Invalid request token');}}
function require_login(): void {if(!user()){header('Location: login.php');exit;}}
function flash($message): void {$_SESSION['flash']=$message;}
function redirect($page): void {header('Location: '.$page);exit;}
