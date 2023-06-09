<?php

class UsersDao {
    function getAllUsers(){
        $db = Flight::db();
        $query = $db->query("SELECT id, name, lastname, ndoc, status, trial, creation_dt FROM users");
        $users = $query->fetchAll(PDO::FETCH_ASSOC);
        return $users;
    }
    
    function getUserByEmail($email){
        $db = Flight::db();
        $query = $db->prepare("SELECT id, name, lastname, ndoc, status, trial, creation_dt FROM users WHERE email = :email");
        $query->bindParam(':email', $email);
        $query->execute();
        $users = $query->fetch(PDO::FETCH_ASSOC);
        return $users;
    }

    function getCurrentUser($workspace_id){
        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            Flight::halt(401);
        }
        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];
        $key = getenv('encryption_key');
        // Consulta o banco de dados para verificar as credenciais do usuário
        $db = Flight::db();
        $query = $db->prepare
        ("SELECT 
        u.id,  
        u.name,  
        u.lastname,  
        u.email,  
        u.ndoc,  
        u.trial,  
        u.status,  
        u.profile_pic,  
        u.profile_pic_mime,  
        u.creation_dt,  
        CONCAT('[',GROUP_CONCAT(
            r.name
            ),']') as roles
        FROM 
            users as u 
        INNER JOIN 
            users_workspaces as uw 
        ON 
            uw.user_id = u.id 
        INNER JOIN
            user_roles as ur
        ON
            ur.user_id = u.id
        INNER JOIN
            roles as r
        ON
            r.id = ur.role_id
        WHERE 
            email = :username
        AND 
            password = AES_ENCRYPT(:password, :encryption_key) 
        AND 
            uw.workspace_id = :workspace_id
        GROUP BY 
            u.id");
        $query->bindParam(':username', $username);
        $query->bindParam(':password', $password);
        $query->bindParam(':workspace_id', $workspace_id);
        $query->bindParam(':encryption_key', $key);
        $query->execute();
        $users = $query->fetch(PDO::FETCH_ASSOC);
        if($users){
            $users['password'] = '';
            $users['profile_pic'] = base64_encode($users['profile_pic']);
            return $users;
        }else{
            return [];
        }
    }
    
    function getUserById($id){
        $db = Flight::db();
        $query = $db->prepare("SELECT id, name, lastname, ndoc, status, trial, creation_dt FROM users WHERE id = :id");
        $query->bindParam(':id', $id);
        $query->execute();
        $users = $query->fetch(PDO::FETCH_ASSOC);
        return $users;
    }
    
    function editUser($body) {
        $db = Flight::db();
        if(!isset($body['password'])){
            $query = $db->prepare("
            UPDATE users SET 
            name = :name,
            lastname = :lastname,
            email = :email,
            ndoc = :ndoc,
            status = :status,
            trial = :trial
            WHERE id = :id
            ");
            $id = $body['id'];
            $name = $body['name'];
            $lastname = $body['lastname'];
            $email = $body['email'];
            $ndoc = $body['ndoc'];
            $status = $body['status'];
            $trial = $body['trial'];
            
            $query->bindParam(':id', $id);
            $query->bindParam(':name', $name);
            $query->bindParam(':lastname', $lastname);
            $query->bindParam(':email', $email);
            $query->bindParam(':ndoc', $ndoc);
            $query->bindParam(':status', $status);
            $query->bindParam(':trial', $trial);
            $query->execute();
            
        }else{
            $query = $db->prepare("
            UPDATE users SET 
            name = :name,
            lastname = :lastname,
            password = AES_ENCRYPT(:password, :key),
            email = :email,
            ndoc = :ndoc,
            status = :status,
            trial = :trial
            WHERE id = :id
            ");
            $id = $body['id'];
            $name = $body['name'];
            $lastname = $body['lastname'];
            $password = $body['password'];
            $status = $body['status'];
            $email = $body['email'];
            $ndoc = $body['ndoc'];
            $trial = $body['trial'];
            $encryption_key = getenv('encryption_key');
            
            $query->bindParam(':id', $id);
            $query->bindParam(':name', $name);
            $query->bindParam(':key', $encryption_key);
            $query->bindParam(':lastname', $lastname);
            $query->bindParam(':status', $status);
            $query->bindParam(':password', $password);
            $query->bindParam(':email', $email);
            $query->bindParam(':ndoc', $ndoc);
            $query->bindParam(':trial', $trial);
            $query->execute();
            
        }
        
        $updatedRows = $query->rowCount();
        if ($updatedRows > 0) {
            return $body; // Retorna os dados atualizados do usuário
        } else {
            return array("success"=> false, "message"=> "Nenhum usuário foi atualizado"); // Nenhum usuário foi atualizado
        }
    }
    
    function editUserProfilePic() {
        if (empty($_POST['id']) || empty($_FILES['image'])) {
            return array("success"=> false, "message"=> "ID e imagem devem ser informados");
        }else{
            $id = $_POST['id'];
            $image = $_FILES['image'];
            $maxFileSize = 2 * 1024 * 1024;
            if ($image['size'] > $maxFileSize) {
                return array("success"=> false, "message"=> "Imagem muito grande");
            }
            $imageData = file_get_contents($image['tmp_name']);
            $db = Flight::db();
            $query = $db->prepare("
            UPDATE users SET 
            profile_pic = :pic,
            profile_pic_mime = :pic_mime
            WHERE id = :id
            ");
            $query->bindParam(':id', $id);
            $query->bindParam(':pic', $imageData, PDO::PARAM_LOB);
            $query->bindParam(':pic_mime', $image['type'], PDO::PARAM_STR);
            $query->execute();
            
            if($query->rowCount() > 0){
                return array("success"=> true, "message"=> "Imagem atualizada com sucesso");
            }else{
                return array("success"=> false, "message"=> "Nenhum usuário foi atualizado");
            }
        }
    }
    
    function getUserImage($id){
        $db = Flight::db();
        $query = $db->prepare("SELECT profile_pic, profile_pic_mime FROM users WHERE id = :id");    
        $query->bindParam(':id', $id);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            // Converte o binário em Base64
            $imageData = base64_encode($result['profile_pic']);
            // Retorna a resposta JSON
            return array("profile_pic"=> $imageData, "profile_pic_mime"=> $result['profile_pic_mime']);
        } else {
            // Retorna um erro se a imagem não for encontrada
            Flight::halt(404, 'Imagem não encontrada');
        }
    }

    function getWorkspacesByUserId($id){
        $db = Flight::db();
        $query = $db->prepare("
        SELECT * 
        FROM 
            users_workspaces as uw 
        INNER JOIN 
            workspaces as w 
        ON 
            w.id = uw.workspace_id 
        WHERE 
            uw.user_id = :user_id");
        $query->bindParam(':user_id', $id);
        $query->execute();
        $data = $query->fetch(PDO::FETCH_ASSOC);
        return $data;
    }
    
    function getUsersByWorkspaceId($workspace_id){
        
    }

}

?>