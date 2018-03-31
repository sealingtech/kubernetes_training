<?php

$hostname = "172.18.0.3";
$username = "docker_man";
$password = "docker12345";
$db = "applications";

$dbconnect=mysqli_connect($hostname,$username,$password,$db);

if ($dbconnect->connect_error) {
  die("Database connection failed: " . $dbconnect->connect_error);
}

if(isset($_POST['submit'])) {
  $name=$_POST['name'];
  $application=$_POST['application'];
  $details=$_POST['details'];

  $query = "INSERT INTO user_application (name, application, details)
  VALUES ('$name', '$application', '$details')";

    if (!mysqli_query($dbconnect, $query)) {
        die('An error occurred. Your review has not been submitted.');
    } else {
      echo "Thanks for your application!";
    }

}
?>

