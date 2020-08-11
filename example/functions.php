<?php

function delete_config(MySQLWork $MW)
{
  echo "<h4>Delete tables TEST1, TEST2, import_task_inf and procedure report_test2</h3>";
  $MW->query("DROP TABLES IF EXISTS TEST1, TEST2, import_task_info;");
  $MW->query("DROP PROCEDURE IF EXISTS report_test2;");
}


function init_config(MySQLWork $MW, XLStoMySQL $XI) : bool
{
  echo "<h4>1. Create table import_task_info</h3>";
  if($XI->create_table_task_info() === true){
    echo "Successfully";
  } else {
    echo "Error: Can't create import_task_info";
    $MW->close();
    return false;
  }

  echo "<h4>2. Create test tables</h3>";
  $query = "CREATE TABLE IF NOT EXISTS TEST1(
    id int NOT NULL AUTO_INCREMENT,
    name varchar(45) NOT NULL,
    birthday varchar(10) DEFAULT NULL,
    tel varchar(16) DEFAULT NULL,
    PRIMARY KEY (id)
  ) ENGINE=InnoDB;";
  $MW->query($query);
  var_dump($query);

  $query = "CREATE TABLE IF NOT EXISTS TEST2(
    id int NOT NULL AUTO_INCREMENT,
    A varchar(255) DEFAULT NULL,
    B varchar(255) DEFAULT NULL,
    PRIMARY KEY (id)
  ) ENGINE=InnoDB;";
  $MW->query($query);
  var_dump($query);

  echo "<h4>3. Insert tasks into import_task_info</h3>";
  $query = "INSERT IGNORE INTO import_task_info(task_name,import_table_info,column_last_name,rows_delete_cnt)
    VALUES('test1 simple', 'TEST1(id,name,birthday,tel)','D',1);";
  var_dump($query);

  $MW->query($query);
  $query = "INSERT IGNORE INTO import_task_info(task_name,import_table_info,column_last_name,rows_empty_allowed_cnt)
    VALUES('test2 simple', 'TEST2(A,B)', 'B', 20);";
  var_dump($query);
  $MW->query($query);

  echo "<h4>4. ADD tasks by object XLStoMySQL</h3>";
  $task_options = [
    'import_table_info' => 'TEST1(name,tel)',
    'column_last_name' => 'D',
    'column_delete_names' => 'A,C',
    'rows_delete_cnt' => 1,
    'json_template' => '{
      "columns_count":4,
      "row_number":1,
      "columns":{"B":"Name","D":"Tel"}
    }'
  ];
  var_dump('test1 with template', $task_options);
  $XI->set_task_from_array('test1 with template', $task_options);
  $XI->save_task_to_db(true);

  $task_options = [
    'import_table_info' => 'TEST2(A,B)',
    'column_last_name' => 'B',
    'rows_empty_allowed_cnt' => 20,
    'column_explicit_names' => 'B',
    'procedure_name' => 'report_test2'
  ];
  $XI->set_task_from_array('test2 with procedure', $task_options);
  $XI->save_task_to_db(true);

  $MW->query('DROP PROCEDURE IF EXISTS report_test2;');
  $query = "CREATE PROCEDURE report_test2()
    BEGIN
    declare full_name, address, birthday, tel, mather_info, father_info varchar(255) default '';
    declare comments, food_al, med_conc varchar(255) default '';

    select B into full_name from TEST2 where id = 3;
    select concat(full_name, ' ', B) into full_name from TEST2 where id = 4;
    select concat(full_name, ' ', B) into full_name from TEST2 where id = 5;
    select B into address from TEST2 where id = 6;
    select B into birthday from TEST2 where id = 7;
    select B into tel from TEST2 where id = 8;
    select B into mather_info from TEST2 where id = 9;
    select concat(mather_info, ', ', B) into mather_info from TEST2 where id = 10;
    select B into father_info from TEST2 where id = 11;
    select concat(father_info, ', ', B) into father_info from TEST2 where id = 12;
    select A into comments from TEST2 where id = 14;
    select A into food_al from TEST2 where id = 16;
    select A into med_conc from TEST2 where id = 18;

    select concat(
      '<b>Full name:</b> ',       full_name,   '<br>',
      '<b>Birthday:</b> ',        birthday,    '<br>',
      '<b>Address:</b> ',         address,     '<br>',
      '<b>Telephone:</b> ',       tel,         '<br>',
      '<b>Mather Info:</b> ',     mather_info, '<br>',
      '<b>Father Info:</b> ',     father_info, '<br>',
      '<b>Comments:</b> ',        comments,    '<br>',
      '<b>Food Allergies:</b> ',  food_al,     '<br>',
      '<b>Medical Concerns:</b> ',med_conc
    ) as card;
    END";
  var_dump('test2 with procedure', $task_options, htmlspecialchars($query) );
  $MW->query($query);

  return true;
}


function check_config(MySQLWork $MW) : bool
{
  $query = "SELECT count(*)=3
    FROM  INFORMATION_SCHEMA.PARTITIONS
    WHERE TABLE_SCHEMA = '" . DATABASE . "' AND TABLE_NAME IN ('TEST1','TEST2','import_task_info');";
  return (int)$MW->oneValueSQL($query) === 1;
}
