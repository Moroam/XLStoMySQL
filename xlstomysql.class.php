<?php
/**
 * Class ImportXLS
 *
 * @version 1.0.0
 */
require_once 'mysqlwork.class.php';
require_once 'xlsread.class.php';
require_once 'dataimporttomysql.class.php';
require_once 'datatest.class.php';
class XLStoMySQL extends DataImportToMySQL{
  # class MySQLWork
  protected $MW = NULL;

  # class DataTest
  protected $data_test = NULL;
  protected $task_name = '';

  /**
   * XLStoMySQL constructor
   *
   * @param object $MW  MySQLWork object
   * @param string $task_name    import task name
   * @param array  $task_options array with import task options
   *
   * @throws Exception  parent::__construct
   * @throws Exception  set_task
   * @throws Exception  get_task_from_db
   */
  public function __construct(MySQLWork $MW, string $task_name = '', array $task_options = []) {

    parent::__construct($MW);

    $this->data_test = new DataTest;

    if($task_name !== ''){
      $this->task_name = $task_name;

      if(count($task_options)>0){
        $this->set_task($task_options);
      } else {
        $this->get_task_from_db();
      }
    }
  }

  /**
   * Set import task_options from array
   *
   * @param array $task_options   task options
   *        import_table_info   - table_name(fild1,fild2,...fildN)
   *        column_last_name    - name of the last column in the XLS table - one letter ('A')
   *        column_delete_names - list columns names separated by comma ('A,C,D')
   *                              which will need to be deleted
   *        column_explicit_names - list columns names separated by comma ('A,C,D')
   *                              which will be read in RAW format (string) without formating
   *                              if not set - XLS table open as READONLE
   *        rows_delete_cnt     - rows from 0 to rows_delete_cnt will be deleted
   *        rows_empty_allowed_cnt - if we mast save empty rows when importing XLS tables
   *        procedure_name      - MySQL procedure which will be launched after successful import
   *        json_template       - JSON tamplate for table head
   *        truncate_table      - if set TRUE will be truncated table from import_table_info
   *        description         - task description
   *
   * @throws Exception  parent::set_task
   * @throws Exception  set_template_array
   */
  public function set_task(array $task_options) : bool {
    if(!parent::set_task($task_options) ){
      return false;
    }

    $this->task_options['column_last_name']      = $task_options['column_last_name']      ?? '';
    $this->task_options['column_delete_names']   = $task_options['column_delete_names']   ?? '';
    $this->task_options['column_explicit_names'] = $task_options['column_explicit_names'] ?? '';
    $this->task_options['rows_delete_cnt']       = $task_options['rows_delete_cnt']       ?? 0 ;
    $this->task_options['procedure_name']        = $this->MW->test($task_options['procedure_name'] ?? '');
    $this->task_options['json_template']         = $task_options['json_template']         ?? '';
    $this->task_options['description']           = $task_options['description']           ?? '';

    return $this->set_template_array();
  }

  /**
   * Read task_options from MySQL table import_table_info
   *
   * @throws Exception  MySQLWork
   * @throws Exception  select $task_options by $task_name
   * @throws Exception  task with $task_name don't exists
   * @throws Exception  set_task
   */
  protected function get_task_from_db(){
    $query = "SELECT import_table_info, column_last_name, column_delete_names, column_explicit_names, rows_delete_cnt, rows_empty_allowed_cnt, procedure_name, json_template, truncate_table FROM import_task_info WHERE task_name='$this->task_name';";
    $result= $this->MW->query($query);
    if(is_string($result)){
      throw new Exception("SQL Execute Error - $result");
    }

    if($result->num_rows === 0){
      throw new Exception("Error: import task '$this->task_name' does not exists");
    }

    $result->data_seek(0);
    $task_options = $result->fetch_assoc();
    $result->free();

    $this->set_task($task_options);
  }

  /**
   * Set task_options from array
   *
   * @param string $task_name     task name
   * @param array $task_options   task options
   * @throws Exception  Task name not set
   * @throws Exception  set_task
   */
  public function set_task_from_array(string $task_name, array $task_options){
    if($task_name === ''){
      throw new Exception("Task name not set");
    }

    $this->task_name = $task_name;
    $this->set_task($task_options);
  }

  /**
   * Set template_array from DB
   *
   * @param string $task_name     task name
   * @throws Exception  Task name not set
   * @throws Exception  get_task_from_db
   */
  public function set_task_from_db(string $task_name){
    if($task_name === ''){
      throw new Exception("Task name not set");
    }

    $this->task_name = $task_name;
    $this->get_task_from_db();
  }

  /**
   * Set template for data_test (object DataTest)
   *
   * @throws set_template
   * @return bool
   */
  protected function set_template_array() : bool {
    if($this->task_options['json_template'] !== ''){
      if($this->data_test->set_template($this->task_options['json_template'])){
        return $this->ok();
      } else {
        return $this->err($this->data_test->error, $this->data_test->errno);
      }
    }

    return $this->ok();
  }

  /**
   * Check data_array for template from data_test
   *
   * @return bool
   */
  protected function test_data_array() : bool {
    if($this->data_test->test($this->data_array)){
      return $this->ok();
    } else {
      return $this->err($this->data_test->error, $this->data_test->errno);
    }
  }

  /**
   * Insert data array to MySQL
   *
   * @param file   $FILE XLS file
   * @param string $sheet          worksheet number for reading
   * @param bool   $run_proc       run MySQL procedure after import
   * @param bool   $truncate_table truncating MySQL table before insert data
   * @param bool   $unsetData      clear data array after successfully adding it to the MySQL
   * @throws Exception   check import table existance
   * @throws Exception   Error import table truncating
   * @return bool
   * @return mysql_result          if set $run_proc
   */
  public function import_file($FILE, string $sheet = '', bool $run_proc = TRUE, bool $truncate_table = TRUE, bool $unsetData = TRUE){
    # 1. check task info
    if(count($this->task_options) === 0){
      return $this->err("The task is not set");
    }

    if(!XLSRead::is_xls_file($FILE)){
      return $this->err(XLSRead::get_error());
    }

    # 2. File to ARRAY
    $this->data_array = XLSRead::xls_to_array($FILE, $this->task_options['column_last_name'], $sheet, $this->task_options['column_explicit_names']);
    if(!is_array($this->data_array)){
      return $this->err("Error reading file");
    }

    # 3. Comparisons data array with template
    if(!$this->test_data_array()){
      return false;
    }

    # 3. Delete firsts rows
    if($this->task_options['rows_delete_cnt'] > 0){
      array_splice($this->data_array, 0, $this->task_options['rows_delete_cnt']);
    }

    # 4. Delete columns
    if($this->task_options['column_delete_names'] !== ''){
      $columns = explode(",", $this->task_options['column_delete_names']);
      foreach ($columns as $key){
        XLSRead::array_delete_col($this->data_array, $key);
      }
    }

    # 5. Import ARRAY to MySQL
    if(!$this->insertArr($truncate_table, $unsetData)){
      return false;
    }

    # 6. Run procedure after import
    if( $run_proc && $this->task_options['procedure_name'] !== ''){
      $query = "call " . $this->task_options['procedure_name'] . "();";
      $result = $this->MW->query($query);
      if(is_string($result)){
        return $this->err($result);
      } else {
        return $result;
      }
    }

    return $this->ok();
  }

  /**
   * Create table with import task information
   *
   * @throws Exception  MySQLWork
   */
  public function create_table_task_info() : bool {
    $query = "CREATE TABLE IF NOT EXISTS import_task_info (
      idimport_task_info int(11) NOT NULL AUTO_INCREMENT,
      task_name varchar(45) NOT NULL,
      import_table_info varchar(1024) NOT NULL,
      column_last_name varchar(4) DEFAULT NULL,
      column_delete_names varchar(255) DEFAULT NULL,
      column_explicit_names varchar(255) DEFAULT NULL,
      rows_delete_cnt int(11) DEFAULT '0',
      rows_empty_allowed_cnt int(11) DEFAULT '0',
      procedure_name varchar(45) DEFAULT NULL,
      json_template varchar(1024) DEFAULT NULL,
      truncate_table boolean DEFAULT TRUE,
      description varchar(255) DEFAULT NULL,
      PRIMARY KEY (idimport_task_info),
      UNIQUE KEY task_name_UNIQUE (task_name)
    ) ENGINE=InnoDB;";

    return $this->MW->query($query);
  }

  /**
   * Save task to DB
   *
   * @throws Exception  MySQLWork
   */
  public function save_task_to_db(bool $update = false) : bool {
    $TO = $this->task_options;
    if($this->task_name === '' || count($TO) === 0){
      return $this->err('Error import task to DB: Empty task');
    }

    $query = "SELECT count(*) FROM import_task_info WHERE task_name='$this->task_name';";
    $cnt = (int)$this->MW->oneValueSQL($query);

    if( $cnt === 0 ){
      $query = "INSERT INTO import_task_info(task_name,import_table_info,column_last_name,column_delete_names,column_explicit_names,
          rows_delete_cnt,rows_empty_allowed_cnt,procedure_name,json_template,truncate_table,description)
        VALUE('$this->task_name','$TO[import_table_info]','$TO[column_last_name]',
          '$TO[column_delete_names]','$TO[column_explicit_names]',$TO[rows_delete_cnt],
          $TO[rows_empty_allowed_cnt],'$TO[procedure_name]','$TO[json_template]',
          $TO[truncate_table],'$TO[description]');";
      $result = $this->MW->query($query);
      return $this->ok("Task '$this->task_name' inserted");
    }

    if( $cnt > 0 && $update){
      $query = "UPDATE import_task_info
        SET import_table_info='$TO[import_table_info]', column_last_name='$TO[column_last_name]',
          column_delete_names='$TO[column_delete_names]',column_explicit_names='$TO[column_explicit_names]',
          rows_delete_cnt=$TO[rows_delete_cnt],rows_empty_allowed_cnt=$TO[rows_empty_allowed_cnt],
          procedure_name='$TO[procedure_name]',json_template='$TO[json_template]',
          truncate_table=$TO[truncate_table],description='$TO[description]'
        WHERE task_name='$this->task_name';";
      $result = $this->MW->query($query);
      return $this->ok("Task '$this->task_name' updated");
    }

    return $this->err("Error: Task '$this->task_name' allready exist");
  }

}
