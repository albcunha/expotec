<?php
/*
 * ServerDataPDO is a class file that wraps data tables SERVER-SIDE processing with PDO (PHP) SQL data abstraction
 * and it provides a simple way to integrate Jquery  data tables with server side databases like SQLite, MySQL and other 
 * PDO supported DB's. It also dynamically renders the Jquery (JAvascript) data tables code and corresponding HTML 
 * (c) Tony Brandao <ab@abrandao.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
date_default_timezone_set('UTC'); // Set default timezone
setlocale(LC_ALL, "en_US.utf8"); // para a questão dos diacríticos

/* Change these to correspond to your database type (dsn) and access credentials, example below uses sqlite w/o pass */  
$db_dsn="sqlite:Folhapessoal.db";  /* corresponds to PDO DSN strings refer to: http://www.php.net/manual/en/pdo.drivers.php */
$db_user=null;   
$db_pass=null; 

/* Sample MySQL Example
$db_dsn= 'mysql:host=localhost;dbname=testdb';
$db_user = 'username';
$db_pass = 'password';
 */

//When called directly via the Jquery Ajax Source look for this
//SECURITY NOTE: Consider moving this if..block to another file if security is a concern.

if ( isset($_GET['oDb']) )    //is this being called from datatables ajax?
{

//Do we have an object database info (Serialized) if so expand it\\
//echo $_GET['oDb'];
$d=unserialize(base64_decode($_GET['oDb']));  //NOTE HARDEN  by encrypting
$pdo = new ServerDataPDO($db_dsn,$db_user,$db_pass,$d['sql'],$d['table'],$d['idxcol']);  //construct the object
$result=$pdo->query_datatables(); //now return the JSON Requested data */
echo $result;
exit;
}



class ServerDataPDO
{
    /* UPDATE these variables with valid PDO DSN and credentials to connect to database */
    /* DSN  information http://www.php.net/manual/en/ref.pdo-mysql.connection.php */
    public $db=array( 
                    "dsn"=> null, 
                    "user"=>null, 
                    "pass"=>null,
                    "conn"=>null,
                    "sql"=>null,
                    "table"=>null, /* DB table to use assigned by constructor*/
                    "idxcol"=>null /* Indexed column (used for fast and accurate table cardinality) */
                    );

    public static $default_ajax_url=__FILE__; //Defaults to current file name
    
    /* Array of database columns which should be read and sent back to DataTables.dynamically created  */
    public $aColumns = null; // holds SELECT [columns] from SQL query
    public $time_start=null; /* Start timer for metric performance collection */
    
/********************************************************************
    constructor function : called when object is first instantiated
*/
    public function __construct($dsn=null,$user=null,$pass=null,$sql=null, $table=null, $index_col=null) 
    { 

    $this->db['dsn']= empty($dsn)? $this->db['dsn'] : $dsn;
    $this->db['sql']= empty($sql)? $this->db['sql'] : $sql;
    $this->db['user']= empty($user)? $this->db['user'] : $user;
    $this->db['pass']= empty($pass)? $this->db['pass'] : $pass;
    
    

    /* Create a database connection if $db['conn'] is null*/
    if (empty( $this->db['conn']) )  /* no valid connection? let's make one */
        $this->pdo_conn($this->db['dsn'],$this->db['user'],$this->db['pass']);
        
    /* Start timer for metrics */
     $this->time_start = microtime(true);
     
     /* build the SQL table and columns from the String */
     if (!empty($sql) )
       $this->get_SQL_acolumns($sql);
      
     /* assign table and index if provided */
    $this->db['idxcol'] = $index_col;
    $this->db['table'] = $table;
    }
    
/********************************************************************
    pdo_conn : Creates a connection to a database vai the PDO (PHP) database abstraction layer
    Refer to http://ca1.php.net/manual/en/pdo.drivers.php  for possible PDO drivers and DSN strings
    Called by the constructor
    @dsn  matches PDO DSN string name for database connection 
    @return  null , sets global $db['conn'] variable
*/
public  function pdo_conn($dsn=null,$username=null,$password=null)
     {

     try {
        //echo "[dsn]: $dsn >> Connection: ".$this->db[ 'conn'];
        $this->db['conn'] = new PDO($dsn);    //typical dsn like  'mysql:host=localhost;dbname=testdb';
        
        // Set errormode to exceptions
        $this->db['conn']->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        return true;
     
    } catch (PDOException $e) {
        $this->fatal_error( "Database Error!: <strong>".$e->getMessage()."</strong> Dsn= <strong>$dsn </strong><br/>" );
        
    }
    
    } //end of connection function
    
/********************************************************************
    get_SQL_acolumns: Uses a basic SQL Statements SELECT field1,field3,field3 FROM Table 1 and extracts SELECT fields
    NOTES: Column names MUST be explicitly noted , SELECT * FROM is not supported
           complex SQL statements not currently supported
           Results of fields names are converted into array in $this->aColumns
    @SQL SQL Statements (basic) to have fields extracted
    @returns false if unable to extract columns otherwise true if  $this->aColumns is successfull
    
*/
    public function get_SQL_acolumns($sql=null,$s1="SELECT",$s2="FROM",$split_on=",")
    { 

    $pattern = "/$s1(.*?)$s2/i";
    if (preg_match($pattern, $sql, $matches)) 
     {
     //  print_r($matches);
    
      $this->aColumns=explode($split_on, $matches[1]);  //return into
 $this->aColumns=array_map('trim',$this->aColumns ); //trim white space   
      }
    else
     {
     $this->fatal_error("NO SQL columns found in $sql string resulting in <strong>".$result."</strong> Be sure to have $split_on delimited values.");
    return false; //string not found
    }

      
    }
    

/********************************************************************
    build_html_datatable:   Static function no object needed to instantiate
    Based on the $this->aColumns array it dynamically builds the HTML code for HTML data tables
    @table_id  table_id for Jquery to refer to allow multiple tables
    @columns a comma separated string of column names defaults SQL columns if null
    @returns string containing the completed HTML data tables
*/
public static function  build_html_datatable($columns=null,$table_id="datatable1")
{
$html=null;
$html_columns=null;

//lets extract the columns names from the string
if ( !empty($columns) ) 
    $columns=explode(",",$columns);
else    
  die(" $columns columns array must be  defined, such as col1,col2,col3");
  
//build the header loop through the array and of columns 
$count_cols=count($columns);
foreach($columns as $key=>$val)
  $html_columns.="<td>".trim($val)."</td>\n";
  
$html = <<< EOT
<!-- Start of Generated HTML Datatables structure -->
<table cellpadding='1' cellspacing='1' border='0' class='display' id='$table_id'>

<thead>
<tr>
$html_columns
</tr>
</thead>
<tbody>
<tr><td colspan='$count_cols' class='dataTables_empty'>Loading data from server

</td></tr>
</tbody>
</table>



EOT;

return $html;
}       
    
/********************************************************************
    fatal_error : Creates a Server Error to be passed ot calling AJAX page
    @sErrorMessage Error message to be returned to browser
*/
static function fatal_error( $sErrorMessage = '' )
    {
        header( $_SERVER['SERVER_PROTOCOL'] .' 500 Internal Server Error ' );
        die( $sErrorMessage );
    }
/********************************************************************
query_array : Create an array from a SQL Query string 
@sql  SQL to be executed and returned
@returns $results an array  a PHP array (2D) of results of SQL
*/
function query_array($sql=null)
{
global $db,$debug;
 
try {   
        if ($debug) 
          $time_start = microtime(true);     
        
        $stmt = $this->db['conn']->prepare($sql);
        $stmt->execute();
        
        if ($debug){
         $time =  microtime(true)- $time_start; 
         echo "<HR>Executed SQL:<strong> $sql </strong> in <strong>$time</strong> s<HR>";
         }
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC); //PDO::FETCH_NUM | PDO::FETCH_ASSOC
      return  $results ;
} catch (PDOException $e) {
    $this->fatal_error(" Database Error!: <strong>". $e->getMessage() ."</strong> SQL: $sql <br /> Using DSN ".$this->db['dsn']."<br/>");
    die();   die();
}

}   

/********************************************************************
    query_datables : Primary server-side data table processing, builds query and returns encoded json
    reads input from the datables query string parameters and builds SQL
    @returns json_encode JSON encoded results compatible with data tables
*/
function query_datatables() 
{
    /** Paging   */
    $sqlMax = "1000";
    $sLimit = "";
    if ( isset( $_POST['iDisplayStart'] ) && $_POST['iDisplayLength'] != '-1' )
    {
        $sLimit = "LIMIT ".intval( $_POST['iDisplayStart'] ).", ".
            intval( $_POST['iDisplayLength'] );
    }
    
    /** Ordering */
    $sOrder = "";
    if ( isset( $_GET['iSortCol_0'] ) )
    {
        $sOrder = "ORDER BY  ";
        for ( $i=0 ; $i<intval( $_GET['iSortingCols'] ) ; $i++ )
        {
            if ( $_GET[ 'bSortable_'.intval($_GET['iSortCol_'.$i]) ] == "true" )
            {
                $sOrder .= "`".$this->aColumns[ intval( $_GET['iSortCol_'.$i] ) ]."` ".
                    ($_GET['sSortDir_'.$i]==='asc' ? 'asc' : 'desc') .", ";
            }
        }

        $sOrder = substr_replace( $sOrder, "", -2 );
        if ( $sOrder == "ORDER BY" )
        {
            $sOrder = "";
        }
    }
    
    
    /** Filtering
     * NOTE this does not match the built-in DataTables filtering which does it
     * word by word on any field. It's possible to do here, but concerned about efficiency
     * on very large tables, and MySQL's regex functionality is very limited
     */
    // Retira acentos da pesquisa
    $normalizeChars = array(
    'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A',
    'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
    'Ï'=>'I', 'Ñ'=>'N', 'Ń'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
    'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
    'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
    'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ń'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
    'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f',
    'ă'=>'a', 'î'=>'i', 'â'=>'a', 'ș'=>'s', 'ț'=>'t', 'Ă'=>'A', 'Î'=>'I', 'Â'=>'A', 'Ș'=>'S', 'Ț'=>'T', "'" => "",
    );  
    $_POST['search']['value'] = strtr($_POST['search']['value'], $normalizeChars);
    
    
    $sWhere = "";
    if ( str_replace(' ', '', $_POST['search']['value']) != "" )
    {
        $sWhere = "WHERE ".$this->db['table']." MATCH '". $_POST['search']['value']."'";
        
            
    /** CORE SQL queries * Get data to display   */
       $sQuery = " SELECT  `".str_replace(" , ", " ", implode("`, `", $this->aColumns))."`
        FROM  ".$this->db['table']."
        $sWhere
        $sLimit
        LIMIT $sqlMax
        ";}
    else
        {$sQuery = " SELECT  `".str_replace(" , ", " ", implode("`, `", $this->aColumns))."`
        FROM  ".$this->db['table']."
        WHERE ".$this->db['table']." MATCH 'PATOS' LIMIT 50;
        ";}
       
    try {   
    
      $aResult  = $this->query_array($sQuery);
      
    } catch (PDOException $e) {
    print "SQL DAtabase Error!: " . $e->getMessage() . "<br/>";
    die();
}
    
    /* Data set length after filtering */
    //TODO : improve efficiency currently does 2X query  to count total records in query
    $sQuery = "SELECT cargos_tot_registros FROM  dados_download LIMIT 1";
    
    $aResultTotal = $this->query_array($sQuery);
    $aResultTotal = substr(implode("",$aResultTotal[0]), 0);
    $iTotal = (int)$aResultTotal;
    

    $sQuery = "SELECT * FROM  dados_download LIMIT 1";
    $bdDetalhes = $this->query_array($sQuery);
    $bdDetalhes = substr(implode("",$bdDetalhes[0]), 0);
    
    //$iTotal = 1000;
    //$iFilteredTotal= $aResultFilterTotal[0]['totalqry'];
    //print_r($aResult);
    $iFilteredTotal= count($aResult);
    

    /* Total data set length */
    //$sQuery = 100;
    //$sQuery = "SELECT municipio_tot_registros FROM  dados_download LIMIT 1";
    
    //$aResultTotal = $this->query_array($sQuery);
    //$aResultTotal = substr(implode("|",$aResultTotal[0]), 25);
    //$iTotal = (int)$aResultTotal;
    /*
     * Output
     */
    date_default_timezone_set('America/Recife');
    $output = array(
        "draw" => intval($_POST['draw']),
        "iTotalRecords" => $iTotal,
        "iTime" => date('d/m/Y G:i:s ', time()),
        "iTotalDisplayRecords" => $iFilteredTotal,
        "aaData" => array(),
        "bdDetalhes" => $bdDetalhes
    );
    
    /* Take the Query Result and resturn  JSON encoded String */
    
    foreach ($aResult as $key => $aRow)
    {
        $row = array();
        for ( $i=0 ; $i<count($this->aColumns) ; $i++ )
            $row[] = $aRow[ $this->aColumns[$i] ];

            $output['aaData'][] = $row;
    }
    
    return json_encode( $output );
    
} //end of function

/********************************************************************
 build_jquery_datables:  Static function no object needed to instantiate
  Builds the Javascript JQuery code to call for the database call use this function
    
*/
public static function  build_jquery_datatable($aDBInfo=null,$table_id="datatable1",$ajax_source_url=null,$datatable_properties=null)
{
$js=null;  //Holds the javascript string
$dba=array("a","b");



$ajax_source_url = is_null($ajax_source_url)? basename(__FILE__) : $ajax_source_url;
if (isset($aDBInfo))
  $serializd_db=base64_encode(serialize($aDBInfo));

/* Edit Jqeury Here */
$js=  <<<EOT
<!-- Start generated Jquery from $ajax_source_url  --->
<script type="text/JavaScript" charset="utf-8">
$(document).ready(function() {

var oData=$('#$table_id').dataTable({
language: {
                "url": "//cdn.datatables.net/plug-ins/1.10.15/i18n/Portuguese-Brasil.json",
                
            },
ajax: function (data, callback, settings) {


            settings.jqXHR = $.ajax( {
                                    "dataType": 'json',
                                    "url": "$ajax_source_url?oDb='$serializd_db'",
                                    "type": "POST",
                                    "data": data,
                                    "success": function (json) {
                oData.fnSettings().oLanguage.sInfoPostFix = '    (processado em '+json.iTime+') ';  
                detalhesArquivo = ''+json.bdDetalhes+''
                callback(json)}
                });
            
        },
responsive: true,
autoWidth:false,
processing: true,
sortClasses:true,
serverSide: true,
initComplete: function (oSettings, json) {
             // Add "Clear Filter" button to Filter
              var btnOK = $('<button class="btnOKDataTableFilter">OK</button>');
              btnOK.appendTo($('#' + oSettings.sTableId).parents('.dataTables_wrapper').find('.dataTables_filter'));
              $('#' + oSettings.sTableId + '_wrapper .btnOKDataTableFilter').click(function () {
                oData.fnFilter($("div.dataTables_filter input").val());
              });
              oData.fnFilterOnReturn(); 
        },
stateSave: false,
deferRender: true,
jQueryUI: true,
scrollInfinite: true,
scrollCollapse: true,
scrolX: true,
sScrollX: "100%",
sScrollXInner: "100%", 
scrollY: '72vh',
scrollCollapse: true,
paging: false,

fixedHeader: {
            header: false,
            footer: false
        },
dom:'Bfrtip',

buttons: [  
            {extend: 'copyHtml5', text: 'Copiar'},
            {extend: 'excelHtml5',
             text: 'Excel',
            customize: function ( xlsx ){
                var sheet = xlsx.xl.worksheets['sheet1.xml'];
                 // muda o cabeçalho para cor verde. 
                 
                 $('row c[r^="L"]', sheet).each( function () {
                    // Get the value and strip the non numeric characters
                        $(this).attr( 's', '55' );
                        $(this).attr( 's', '53' );
                                                

                        }
                        );
                 $('row:first c', sheet).attr( 's', '42' );
                 // ajusta coluna m
                
                }
        },
            

            {extend: 'pdfHtml5',
             'text': 'PDF',
             pageSize: 'A4',
             filename : 'Consulta de Cargos no TCE-PB',
             title : ' ',
             footer : true,
             columns:':visible',
             newline:'auto',
             exportOptions: {
                modifier: {
                    page: 'current',
                }
              },
              customize: function ( doc ) {
                    
                    doc['footer']=(function(page, pages) {
                    return {
                    columns: [
                    '',
                    {
                    alignment: 'right',
                    text: [ 'Pág. ',
                    { text: page.toString(), italics: true },
                    ' de ',
                    { text: pages.toString(), italics: true }
                    ]
                    }
                    ],
                    margin: [10, 0]
                    }
                    }),

                    

                    doc.defaultStyle.fontSize = 7,
                    doc.styles.tableHeader.fontSize = 7,
                    doc.content[1].table.widths = [ '15%', '10%', '15%', '10%', '8%', '8%', '11%','12%','11%'],
                    doc.pageMargins = [20,30,20,20],
                    doc.content[0].text = doc.content[0].text.trim(),

                    doc.content.splice( 1, 0, {
                        margin: [ 0, 0, 0, 0 ],
                        alignment: 'left',
                        image: 'data:image/png;base64,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',
                        width: 560,
                        height: 77},
                        doc.content[1].text = detalhesArquivo ,


                        );


                    }
              },
        {extend: 'print', text: 'Imprim.'}
        ]



 } );
           
 
} ); 



</script>

<!--  End generated Jquery from  $ajax_source_url ---> 
EOT;

return $js;  //returns the completed jquery string
}   

} //end of class    


?>
