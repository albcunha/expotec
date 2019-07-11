<!DOCTYPE html>
<html>
   <head>


 <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jqc-1.12.4/jszip-3.1.3/pdfmake-0.1.27/dt-1.10.15/b-1.3.1/b-colvis-1.3.1/b-html5-1.3.1/b-print-1.3.1/cr-1.3.3/fc-3.2.2/fh-3.1.2/r-2.1.1/sc-1.4.2/se-1.2.2/datatables.min.css"/>


 <link rel="stylesheet" type="text/css" href="ajuste-colunas.css">

<script type="text/javascript" src="https://cdn.datatables.net/v/dt/jqc-1.12.4/jszip-3.1.3/pdfmake-0.1.27/dt-1.10.15/b-1.3.1/b-colvis-1.3.1/b-html5-1.3.1/b-print-1.3.1/cr-1.3.3/fc-3.2.2/fh-3.1.2/r-2.1.1/sc-1.4.2/se-1.2.2/datatables.min.js"></script>

<script type="text/javascript" src="https://cdn.datatables.net/plug-ins/1.10.15/api/fnFilterOnReturn.js"></script>



    <?php
    include "server_processingV3.php"; // this class handles is both server and client side data
    //www.abrandao.com/lab/datatable_pdo/client.php
    //now generate the datatables Jquery with this SQL here:
    //for added security move this array into to the serverside client
    $db_array=array(
    /* Spell out columns names no SELECT * Table */
    
    "sql"=>'SELECT servidor, cpf, cargo, natureza, mes_ano, remuneracao, poder, orgao, data_admissao FROM folhaparaiba',
    "table"=>'folhaparaiba', /* DB table to use assigned by constructor*/
    "idxcol"=>'ID' /* Indexed column (used for fast and accurate table cardinality) */
    );
    //Custom datatables properties added to Jquery build datatable
    $javascript = ServerDataPDO::build_jquery_datatable($db_array);
    echo $javascript;
    ?>

    <meta charset=utf-8 />
    <title>CARGOS TCE</title>
 

  </head>
    <body>
     <span style = "color:red"> <b> CARGOS TCE. (Limite de linhas: 1.000) </b> </span>
        <hr>   
        <?php  
        //now generate the HTML databable structure from SQL   here:
         $cols=" servidor, cpf, cargo, natureza, mes_ano, remuneracao, poder, orgao, data_admissao";  //Column names for datatable headings (typically same as sql)
        $html =ServerDataPDO::build_html_datatable($cols);
        echo  $html;
        ?>
        

    </div>
</body>
</html>




