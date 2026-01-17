<?php
            
            $result = json_decode(file_get_contents('http://ajooba.io/license/api/validate/host/'.$_SERVER['SERVER_NAME'].'/4'), true);

            if($result['status'] != 200) {
            $html = "<div align='center'>
    <table width='100%' border='0' style='padding:15px; border-color:#F00; border-style:solid; background-color:#FF6C70; font-family:Tahoma, Geneva, sans-serif; font-size:22px; color:white;'>

    <tr>

        <td><b>You don't have permission to use this product. The message from server is: <%returnmessage%> <br > Contact the product developer.</b></td >

    </tr>

    </table>

</div>";
            $search = '<%returnmessage%>';
            $replace = $result['message'];
            $html = str_replace($search, $replace, $html);


            die( $html );

            }
            ?>


