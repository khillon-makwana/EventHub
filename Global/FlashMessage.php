<?php
class FlashMessage{
    // for handling session-based flash messages (temporary alerts that show once, like “Registration successful” or “Invalid password”).
    public function setMsg($name, $value, $class){
        if(is_array($value)){
            $_SESSION[$name] = $value;
        }else{
            $_SESSION[$name] = "<div class='alert alert-".$class."' role='alert'>".$value."</div>";
        }
    }

    public function getMsg($name){
        if(isset($_SESSION[$name])){
            $msg = $_SESSION[$name];
            unset($_SESSION[$name]);
            return $msg;
        }
        return null;
    }

    public function hasMessages($name = null) {
        if ($name !== null) {
            return isset($_SESSION[$name]) && !empty($_SESSION[$name]);
        }
        return !empty($_SESSION); 
    }

}