<?php
/*************************************************************

 File: cyradm-php.lib
 Author: gernot
 Revision: 2.0.0
 Date: 2000/08/11

 This is a completely new implementation of the IMAP Access for
 PHP. It is based on a socket connection to the server an is 
 independent from the imap-Functions of PHP

 Copyright 2000 Gernot Stocker <muecketb@sbox.tu-graz.ac.at>

 You should have received a copy of the GNU Public
 License along with this package; if not, write to the
 Free Software Foundation, Inc., 59 Temple Place - Suite 330,
 Boston, MA 02111-1307, USA.

 
 THIS PROGRAM IS AS IT IS! THE AUTHOR TAKES NO RESPONSABILTY ABOUT 
 EVENTUAL DEMAGES, SECURITS-HOLES OR ATTACKES, WHICH COULD BE ENABLED 
 BY THIS PROGRAM

 
 ***************************************************************/


class cyradm {

  var $host;
  var $port;
  var $mbox;
  var $list;

  var $admin;
  var $pass;
  var $fp;
  var $line;
  var $error_msg;

/*
#
#Konstruktor
#
*/
function cyradm(){
  global $CYRUS_USERNAME,$CYRUS_PASSWORD,$CYRUS_HOST,$CYRUS_PORT;
  $this->host   = $CYRUS_HOST;
  $this->port   = $CYRUS_PORT;
  $this->mbox   = "";
  $this->list   = array();

  $this->admin  = $CYRUS_USERNAME;
  $this->pass   = $CYRUS_PASSWORD;
  $this->fp     = 0;
  $this->line   = "";
  $this->error_msg  = "";
} 


/*
#
# SOCKETLOGIN on Server via Telnet-Connection!
#
*/
  function imap_login() {
    $this->fp = fsockopen($this->host, $this->port, &$errno, &$errstr);
    $this->error_msg=$errstr;
    if(!$this->fp) {
      echo "<br>ERRORNO: ($errno) <br>ERRSTR: ($errstr)<br><hr>\n";
    } else {
      $this->command(". login \"$this->admin\" \"$this->pass\"");
    }
    return $errno;
  }


/*
#
# SOCKETLOGOUT from Server via Telnet-Connection!
#
*/
  function imap_logout() {
    $this->command(". logout");
    fclose($this->fp);
  } 


/*
#
# SENDING COMMAND to Server via Telnet-Connection!
#
*/
function command($line) {
    /* print ("$line <br>"); */
    $result = array();
    $i=0; $f=0;
    $returntext="";
    $r = fputs($this->fp,"$line\n");
    while (!((strstr($returntext,". OK")||(strstr($returntext,". NO"))||(strstr($returntext,". BAD")))))
       {
         $returntext=$this->getline();
         /* print ("$returntext <br>"); */
	 if ($returntext) 
	  {
           if (!((strstr($returntext,". OK")||(strstr($returntext,". NO"))||(strstr($returntext,". BAD")))))
	     {
	      $result[$i]=$returntext;
	     }
  	   $i++;
	  }
       }
       
    if (strstr($returntext,". BAD")||(strstr($returntext,". NO"))) 
     {
       $result[0]="$returntext";
       $this->error_msg  = $returntext;

      if (( strstr($returntext,". NO Quota") ))
      {
      
      }
      else
      {
       /*
       print "<br><font color=red><hr><H1><center><blink>ERROR: </blink>UNEXPECTED IMAP-SERVER-ERROR</center></H1><hr><br>
              <table color=red border=0 align=center cellpadding=5 callspacing=3>
	      <tr><td><font color=red>SENT COMMAND: </font></td><td><font color=red>$line</font></td></tr>
	      <tr><td><font color=red>SERVER RETURNED:</font></td><td></td></tr>
	      ";
              for ($i=0; $i < count($result); $i++) {
	       print "<tr><td></td><td><font color=red>$result[$i]</font></td></tr>";
	      }
       print "</table><hr><br><br></font>";
       */
       return false;
     }
    }
    return $result;  
  }


/*
#
# READING from Server via Telnet-Connection!
#
*/

  function getline() {
        $this->line = fgets($this->fp, 256);
	return $this->line;
       }
       
/*
#
# QUOTA Functions
#
*/

// GETTING QUOTA

  function getquota($mb_name) {
    $output=$this->command(". getquota \"$mb_name\"");
    if (strstr($output[0],". NO"))
    {
     $ret["used"] = "NOT-SET";
     $ret["qmax"] = "NOT-SET";
    }
    else
    {
     $realoutput = str_replace(")", "", $output[0]);
     $tok_list = split(" ",$realoutput);
     $si_used=sizeof($tok_list)-2;
     $si_max=sizeof($tok_list)-1;
     $ret["used"] = str_replace(")","",$tok_list[$si_used]);
     $ret["qmax"] = $tok_list[$si_max];
    }
    return $ret;
  }  


// SETTING QUOTA

  function setmbquota($mb_name, $quota) {
    $this->command(". setquota \"$mb_name\" (STORAGE $quota)");
  }



/*
#
# MAILBOX Functions
#
*/

  function createmb($mb_name) {
    $this->command(". create \"$mb_name\"");
  }
                                                                                   
  
  function deletemb($mb_name) {
    $this->command(". setacl \"$mb_name\" $this->admin lrswipcda");
    $this->command(". delete \"$mb_name\"");
  }
  
  function renamemb($mb_name, $newmbname) {
     $all="lrswipcda";
     $this->setacl($mb_name, $this->admin,$all);
     $this->command(". rename \"$mb_name\" \"$newmbname\"");
     $this->deleteacl($newmbname, $this->admin);
    }
    
  function renameuser($from_mb_name, $to_mb_name) {
     $all="lrswipcda"; $find_out=array(); $split_res=array(); $owner=""; $oldowner="";
     
     /* Anlegen und Kopieren der INBOX */
     $this->createmb($to_mb_name);
     $this->setacl($to_mb_name, $this->admin,$all);     
     $this->copymailsfromfolder($from_mb_name, $to_mb_name);

     /* Quotas uebernehmen */  
     $quota=$this->getquota($from_mb_name);
     $oldquota=trim($quota["qmax"]);

     if (strcmp($oldquota,"NOT-SET")!=0) {
       $this->setmbquota($to_mb_name, $oldquota);
      }
     
     /* Den Rest Umbenennen */
     $username=str_replace(".","/",$from_mb_name);
     $split_res=explode(".", $to_mb_name);
     if (strcmp($split_res[0],"user")==0) {
	$owner=$split_res[1];
       }
     $split_res=explode(".", $from_mb_name);
     if (strcmp($split_res[0],"user")==0) {
	$oldowner=$split_res[1];
       }
                 
     $find_out=$this->GetFolders($username);
     
     for ($i=0; $i < count($find_out); $i++) {
       
        if (strcmp($find_out[$i],$username)!=0) {
	  $split_res=split("$username",$find_out[$i]);
	  $split_res[1]=str_replace("/",".",$split_res[1]);
	  $this->renamemb((str_replace("/",".",$find_out[$i])), ("$to_mb_name"."$split_res[1]"));
          if ($owner) {
	   $this->setacl(("$to_mb_name"."$split_res[1]"),$owner,$all);
	  }
          if ($oldowner) {
	   $this->deleteacl(("$to_mb_name"."$split_res[1]"),$oldowner);
	  }
	};
       }
     $this->deleteacl($to_mb_name, $this->admin);
     $this->imap_logout();
     $this->imap_login();
     $this->deletemb($from_mb_name);
    }
    
  function copymailsfromfolder($from_mb_name, $to_mb_name) {
     $com_ret=array();
     $find_out=array();
     $all="lrswipcda";
     $mails=0;

     $this->setacl($from_mb_name, $this->admin,$all);
     $com_ret=$this->command(". select $from_mb_name");
     for ($i=0; $i < count($com_ret); $i++) {
        if (strstr( $com_ret[$i], "EXISTS")) 
	   { 
	    $findout=explode(" ", $com_ret[$i]);
	    $mails=$findout[1];
	   }
       }
     if ( $mails != 0 ) {
      $com_ret=$this->command(". copy 1:$mails $to_mb_name");
      for ($i=0; $i < count($com_ret); $i++) {
	       print "<font color=red>$com_ret[$i]</font><br>";
       }
      }
     $this->deleteacl($from_mb_name, $this->admin);  
    }

/*
#
# ACL Functions
#
*/

  function setacl($mb_name, $user, $acl) {
    $this->command(". setacl \"$mb_name\" \"$user\" $acl");
  }
  

  function deleteacl($mb_name, $user) {
    $result=$this->command(". deleteacl \"$mb_name\" \"$user\"");
  }

  
  function getacl($mb_name) {
    $aclflag=1; $tmp_pos=0;
    $output = $this->command(". getacl \"$mb_name\"");
    $output = explode(" ", $output[0]);
    $i=count($output)-1;
    while ($i>3) {
      if (strstr($output[$i],'"')) {
         $i++;
        }
    
      if (strstr($output[$i-1],'"')) {
        $aclflag=1;
	$lauf=$i-1;
        $spacestring=$output[$lauf];
	$tmp_pos=$i;
        $i=$i-2;
	while ($aclflag!=0)	
        {
	 $spacestring=$output[$i]." ".$spacestring;
	 if (strstr($output[$i],'"')) { $aclflag=0; }
	 $i--; 
	}
	$spacestring=str_replace("\"","",$spacestring);
	if ($i>2) {
	  $ret[$spacestring] = $output[$tmp_pos];
	}	 
      }
      else
      { 
       $ret[$output[$i-1]] = $output[$i];
       $i = $i - 2;
      }
    }
    return $ret;
  }

/*
#
# Folder Functions
#
*/

 function GetFolders($username){
    $username=str_replace("/",".",$username);
    $output = $this->command(". list \"$username\" *");
    
    for ($i=0; $i < count($output); $i++) {
       $splitfolder=split("\"",$output[$i]);
       $output[$i]=str_replace(".","/",$splitfolder[3]);
      }
    return $output;
    }

 function EGetFolders($username){
    $lastfolder=split("/",$username);
    $position=count($lastfolder)-1;
    $last=$lastfolder[$position];
    $username=str_replace("/",".",$username);
    $output = $this->command(". list \"$username\" *");
    
    for ($i=0; $i < count($output); $i++) {
       $splitfolder=split("\"",$output[$i]);
       $currentfolder=split("\.",$splitfolder[3]);
       $current=$currentfolder[$position];
       // echo "<br>FOLDER:($) CURRENTFOLDER:($splitfolder[3]) CURRENT:($current) LAST:($last) POSITION:($position)<br>";
       if (strcmp($current,$last)==0){
             $newoutput[$i]=str_replace(".","/",$splitfolder[3]);
	     }
      }
    return $newoutput;
    }


/*
#
# Folder-Output Functions
#
*/

 function GenerateFolderList($folder_array, $username) 
  {
    print "<table border=0 align=center>";
    for ($l=0; $l<count($folder_array); $l++) 
          {
	   echo "<tr><td><a href=\"acl.php?username=",
	   urlencode($username),
	   "&folder=",
	   urlencode($folder_array[$l]),
	   "\">/$folder_array[$l]</a></td></tr>\n";
          };
      print "</table>";
   }
 

 function GetUsers($char="") {
    $users = array();
    $this->imap_login();
    $output=$this->GetFolders("user.$char");
    $this->imap_logout();
    $j = 0;
    $prev = 0;
    for ($i=0; $i < count($output); $i++) {
      $username = split("/", $output[$i],-1);
      $this->debug("($username[1]), 
       $users[$prev])");
       if ((isset($username)) && (isset($users))) {
         if (strcmp($username[1], $users[$prev])) {
	  $users[$j] = $username[1];
	  $j++;
          }
	 }
      if ($j != 0) { $prev = $j - 1; }
    }
    return $users;
  }
  
 function debug($message) {
//      echo "<hr>$message<br><hr>";
 }


} //KLASSEN ENDE

  
?>
