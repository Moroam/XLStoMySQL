<!DOCTYPE html>
<html lang=ru>
<meta charset=utf-8>
<head>
  <title>Project - Import XLS to MySQL</title>
</head>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">

<style type="text/css">
  form {
    width: 50%;
    min-width: 20em;
    max-width: 100em;
  }
  pre {
    padding-left: 2em;
    font-size: 75%;
  }
</style>

<body>

<?php
error_reporting(E_ALL);
if(ini_set('display_errors', 1)===false)
  echo "ERROR INI SET";

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;

require 'functions.php';
require_once 'connection.php';
require_once 'xlstomysql.class.php';

$MW = new MySQLWork(HOST, USER, PASSWORD, DATABASE);

if(!$MW->mysqliTest()){
  echo "<h4>Connection ERROR!!!</h3>";
  goto END;
}

$XI = new XLStoMySQL($MW);

if( isset($_POST['btndelete']) || isset($_POST['btninit'])){
  echo "<details><pre>";

  if(isset($_POST['btndelete'])){
    delete_config($MW);
  }

  if(isset($_POST['btninit']) && !init_config($MW, $XI)){
    echo "Init config Error";
    goto END;
  }

  echo "</pre></details>";
}

$init = check_config($MW);
$disabled_init = $init ? '' : 'disabled';
$disabled_del = $init ? 'disabled' : '';

$task_name = $MW->TIP('task_name');
$FORMATS = $init ? $MW->arraySQL("SELECT task_name FROM import_task_info;", FALSE) : [] ;

?>
<form method='post' enctype='multipart/form-data' class="border rounded mx-auto p-3 mt-5">
  <h3 class="text-center">File imports - XLStoMySQL</h3>

  <div class="form-group">
    <select class="form-control" name="task_name" <?=$disabled_init?> >
      <option selected disabled>Select import task</option>
      <?php
        foreach($FORMATS as $key => $val)
          echo "<option value='$val[0]'".($val[0]===$task_name ? " selected>" : ">")."$val[0]</option>";
      ?>
    </select>
  </div>

  <div class="form-group">
    <input type="file" class="form-control-file" id="inputfile" name='inputfile' <?=$disabled_init?> >
  </div>

  <div class="text-center">
    <input type='submit' class="btn btn-primary" name='btnimport' value='Import' <?=$disabled_init?> />
    <input type='submit' class="btn btn-success" name='btninit'   value='Init'
      title="Create test tables and set import options" <?=$disabled_del?>/>
    <input type='submit' class="btn btn-danger"  name='btndelete' value='Delete configs'
      title="Delete test tables and import options" <?=$disabled_init?> />
  </div>

</form>

<?php

if(!isset($_POST['btnimport'])){
  goto END;
}

if($task_name === ''){
  echo "<h3 align='center'>Task not set!!!</h3>";
  goto END;
}

if($_FILES['inputfile']['name'] === ''){
  echo "<h3 align='center'>File not select!!!</h3>";
  goto END;
}

$XI->set_task_from_db($task_name);
$result = $XI->import_file($_FILES['inputfile']);

if(is_object($result) && get_class($result) === 'mysqli_result'){
  echo $MW->htmlTable($result);
} elseif($result === true) {
  echo '<h4>Successfuly imported!</h4>';
} else {
  echo '<h4>File import error: ' . $XI->errno . ' - ' . $XI->error . '</h4>';
}

if($result !== false){
  $table_name = strtoupper(substr($task_name, 0, 5));
  $res = $MW->query("select * from $table_name;");
  echo $MW->htmlTable($res, 'Task - ' . $task_name);
}

END:
$MW->mysqliTest() && $MW->close();

?>
</body>
</html>
