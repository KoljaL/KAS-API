<?php

/**
 * Login and get SessionToken from the API
 *
 *  INPUT
 *  $user  = KAS Account login name
 *  $passw = KAS Account login password
 * 'KasRequestType' => 'get_mailaccounts'
 *
 *  OUTPUT
 *  $CredentialToken
 *  $req = array with all mailaccounts // echo "<pre>";print_r($req);echo "</pre>";
 */

 // Login
 $user  = 'xxxx';
 $passw = 'xxx';

$Params = array(
    'KasUser' => $user,
    'KasAuthType' => 'sha1',
    'KasPassword' => sha1($passw) ,
    'SessionLifeTime' => 600,
    'SessionUpdateLifeTime' => 'Y'
);

try
{
    $SoapLogon = new SoapClient('https://kasapi.kasserver.com/soap/wsdl/KasAuth.wsdl');
    $CredentialToken = $SoapLogon->KasAuth(json_encode($Params));
    //	echo "Ihr SessionToken lautet: $CredentialToken <br>"; //  bd6d56c7a992c53e521410ef067e13dc

}
catch(SoapFault $fault)
{
    trigger_error("Fehlernummer: {$fault->faultcode},
					Fehlermeldung: {$fault->faultstring},
					Verursacher: {$fault->faultactor},
					Details: {$fault->detail}", E_USER_ERROR);
}

// get Mailaccounts
try
{
    $SoapRequest = new SoapClient('https://kasapi.kasserver.com/soap/wsdl/KasApi.wsdl');
    $req = $SoapRequest->KasApi(json_encode(array(
        'KasRequestParams' => $Params, // Parameter an die API-Funktion
        'KasUser' => $Params['KasUser'], // KAS-User
        'KasAuthType' => 'session', // Auth per Sessiontoken
        'KasAuthData' => $CredentialToken, // Auth-Token
        'KasRequestType' => 'get_mailaccounts' 
    )));
}

catch(SoapFault $fault)
{
    trigger_error(" Fehlernummer: {$fault->faultcode},
                    Fehlermeldung: {$fault->faultstring},
                    Verursacher: {$fault->faultactor},
                    Details: {$fault->detail}", E_USER_ERROR);
}


/**
 *  Websiteheader
 */
?>
<style>
.body { padding-left: 50px;}
</style>
<div class="body">
<h1>  Emailverteiler </h1>
<?php



/**
 *  Dropdown Menu with all Emailadresses
 *
 *  INPUT
 *  $req
 *
 *  OUTPUT
 *  $_POST["verteiler"] = Emailadress of choosen List
 *
 */
?>
<form action="" method="post">
	  <select name="verteiler" id="verteiler">
		  <option selected="selected">Verteiler ausw&auml;hlen</option>
			<?php for ($i = 0;$i < count($req["Response"]["ReturnInfo"]) ;$i++)
			{
			    if ($req["Response"]["ReturnInfo"][$i]["mail_adresses"] != "admin@domain.de")
			    		{ ?>
							<option value="<?=$req["Response"]["ReturnInfo"][$i]["mail_adresses"] ?>"><?=$req["Response"]["ReturnInfo"][$i]["mail_adresses"] ?></option>
							<?php
							}
			} ?>
		</select>
		<label><input type="submit" value="OK" /><label>
</form>
<br>
<?php




/**
 *  Users of Mailinglist as text
 *
 *  INPUT
 *  $_POST["verteiler"] = Emailadress of choosen List
 *
 *  OUTPUT
 *  $_POST["edit_verteiler"] = Emailadress of List to edit
 *
 */
// show userlist
if (isset($_POST["verteiler"]))
{
    $key   = array_search($_POST["verteiler"], array_column($req["Response"]["ReturnInfo"], 'mail_adresses'));
    $liste = $req["Response"]["ReturnInfo"][$key]["mail_copy_adress"];
    $liste = str_replace(",", "<br>", $liste);
    echo "<h3> Verteiler: " . $_POST["verteiler"] . "</h3>";
    echo $liste;

// show edit button
		?>
	  <br>
	  <form action="" method="post">
		  <input type="hidden" size="17" value=<? echo $_POST["verteiler"] ?> name="edit_verteiler">
			<br><br>
		  <input type="submit" value="Verteiler Bearbeiten">
	  </form>
	  <?php
}

/**
 *  Users of Mailinglist in textarea
 *
 *  INPUT
 *  $_POST["edit_verteiler"] = Emailadress of List to edit
 *
 *  OUTPUT
 *  $_POST["copy_adress"] = new list of users
 *  $_POST["mail_login"] = mail_login (m05257c4) of List to edit
 *  $_POST["verteiler"] = Emailadress of List to edit to call the listview
 */
if (isset($_POST["edit_verteiler"]))
{
    $key   = array_search($_POST["edit_verteiler"], array_column($req["Response"]["ReturnInfo"], 'mail_adresses'));
    $mail_login = $req["Response"]["ReturnInfo"][$key]["mail_login"];
    $liste = $req["Response"]["ReturnInfo"][$key]["mail_copy_adress"];
    $liste = str_replace(" ", "", $liste);
    $liste = str_replace(",", "\n", $liste);
    echo "<h3> Verteiler: " . $_POST["edit_verteiler"] . " bearbeiten</h3>";
    echo "<p>pro Zeile eine Emailadresse, keine Kommata oder &auml;hnliches zum trennen <br> wenn versehentlich zuviel gel&ouml;scht, einfach den Verteiler nochmal ausw&auml;hlen</p>"
?>
  <form action="" method="post">
        <textarea id="text" name="copy_adress" cols="55" rows="15"><? echo $liste; ?></textarea>
				<input type="hidden" size="17" value=<? echo $mail_login ?> name="mail_login">
        <input type="hidden" size="17" value=<? echo $_POST["edit_verteiler"] ?> name="verteiler_name">
        <br><br><br>
        <input type="submit" value="Absenden" />
  </form>
<?php

}

/**
 *  Update Mailaccount & make backup
 *
 *  INPUT
 *  $_POST["copy_adress"] = new list of users
 *  $_POST["mail_login"] = mail_login (m05257c4) of List to edit
 *  $_POST["verteiler_name"] = Emailadress of List to edit
 *
 *  OUTPUT
 *  backup/01_01_1970.txt Backup file
 */
if (isset($_POST["mail_login"]))
{
    // erstmal n backup
    file_put_contents("backup/" . date('Y_m_d_H_i_s') . ".txt", serialize($req));
		// no linebreaks, but commata
    $liste = str_replace("\n", ", ", $_POST["copy_adress"]);
    // run KAS API
    try
    {
        $Params1 = array(
            'mail_login'  => $_POST["mail_login"],
            'copy_adress' => $liste
        );

        $SoapRequest = new SoapClient('https://kasapi.kasserver.com/soap/wsdl/KasApi.wsdl');
        $req = $SoapRequest->KasApi(json_encode(array(
            'KasRequestParams' => $Params1, // Parameter an die API-Funktion
            'KasUser' => $Params['KasUser'], // KAS-User
            'KasAuthType' => 'session', // Auth per Sessiontoken
            'KasAuthData' => $CredentialToken, // Auth-Token
            'KasRequestType' => 'update_mailaccount' // API-Funktion
        )));
    }

    catch(SoapFault $fault)
    {
        trigger_error(" <br> Fehlernummer: {$fault->faultcode} <br>
                    Fehlermeldung: {$fault->faultstring}<br>
                    Verursacher: {$fault->faultactor}<br>
                    Details: {$fault->detail}", E_USER_ERROR);
    }

		if ($req["Response"]["ReturnString"] == 'TRUE')
		{
		    echo '<p>&Auml;nderung eingetragen</p>';
		}

		// go back to listview
		?>
		<script language="JavaScript" type="text/javascript">
    		var t = setTimeout("document.myform.submit();",2000);  
		</script>
		<form name="myform" action="" method="post">
		   <input type="hidden" size="17" value=<? echo $_POST["verteiler_name"]; ?> name="verteiler">
	  </form>
		<?php
    // echo "<pre>";
    // print_r($req);
    // echo "</pre>";
}



///////////////////////////////////  Websitefooter  ///////////////////////////////////
?>
<div>
<?php




?>
