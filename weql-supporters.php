<?php
/*
Plugin Name: WEQL Plugin
Plugin URI: https://github.com/iggyvolz/WEQL-Supporters-Plugin
Description: Plugin for the WEQL Griffins site to show supporters directly through PayPal
Version: 0.1
Author: iggyvolz
Author URI: https://iggyvolz.github.io
*/
require_once("htmlElement/htmlElement.php");
define("WEQL_VERSION","0.1");
if(!function_exists("add_action"))
{
  require_once(dirname(dirname(dirname(__DIR__)))."/wp-blog-header.php");
  if(isset($_GET["action"]))
  {
    switch($_GET["action"]):
      case "complete":
        if(wp_verify_nonce($_GET["_wpnonce"],"complete_".$_GET["id"]))
        {
          weql_complete_actionitem($_GET["id"]);
        }
        break;
      case "uncomplete":
        if(wp_verify_nonce($_GET["_wpnonce"],"uncomplete_".$_GET["id"]))
        {
          weql_uncomplete_actionitem($_GET["id"]);
        }
        break;
      case "register":
        weql_register_donor($_GET["id"],$_GET["name"],$_GET["nonce"]);
    endswitch;
  }
}
add_shortcode("weql-supporters", "weql_display_supporters");
add_shortcode("weql-register", "weql_display_register");
add_action( 'wp_dashboard_setup', 'weql_add_actionitems_widget' );
function weql_add_actionitems_widget()
{
  wp_add_dashboard_widget("weql-action-items","Action Items","weql_display_action_items");
}

function weql_display_supporters()
{
  global $wpdb;
  $table_name=$wpdb->prefix."weql_donors";
  ob_start();
  foreach(["bronze"=>[100,499],"silver"=>[500,1499],"gold"=>[1500,4999],"platinum"=>[5000,9999],"legendary"=>[10000,99999]] as $type=>$amounts)
  {
    list($min,$max)=$amounts;
    $header=new htmlElement("h3");
    while($header->toggle())
    {
      echo ucfirst($type);
    }
    $supporters=$wpdb->get_col("SELECT name FROM ${table_name} WHERE amount>=${min} AND amount<=${max}");
    foreach($supporters as $supporter)
    {
      $p=new htmlElement("p");
      while($p->toggle())
      {
        echo $supporter;
      }
    }
  }
  return ob_get_clean();
}
function weql_display_register()
{
  ob_start();
  ?><script>function getUrlVars() { // Courtesy http://papermashup.com/read-url-get-variables-withjavascript/
var vars = {};
var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
vars[key] = value;
});
return vars;
}
jQuery("#weql_donation_submit").click(function(){var name=encodeURIComponent (jQuery("#weql_donation_name").val());jQuery("#weql_containingspan").html("Processing...");jQuery.get("http://weqlgriffins.tk/wp-content/plugins/weql-supporters/weql-supporters.php?action=register&id="+getUrlVars()["id"]+"&nonce="+getUrlVars()["nonce"]+"&name="+name).done(function(){jQuery("#weql_containingspan").html("Complete!  Expect a follow-up email shortly.")}).fail(function(){jQuery("#weql_containingspan").html("Uh-oh!  Something went wrong.  Try refreshing the page and trying again, or respond to our email with your name so we can put it in manually.")})});</script><?php
  return ob_get_clean();
}

function weql_display_action_items()
{
  global $wpdb;
  $table_name=$wpdb->prefix."weql_actionitems";
  require_once("htmlElement/htmlElement.php");
  $items=$wpdb->get_col("SELECT id FROM ${table_name} WHERE assignee=".get_current_user_id());
  if(empty($items))
  {
    echo "No action items!";
  }
  else
  {
    $script=new htmlElement("script");
    while($script->toggle())
    {
      ?>
      function weql_dashboard_toggle(id)
      {
        if(!jQuery("#weql_checkbox_"+id)[0].checked)
        {
          jQuery.get(jQuery("#weql_uurl_"+id).val());
          jQuery("#weql_p_"+id).css("color","");
          jQuery("#weql_p_"+id).css("text-decoration","");
          jQuery("#weql_containingspan").prepend(jQuery("#weql_p_"+id));
        }
        else
        {
          jQuery.get(jQuery("#weql_curl_"+id).val());
          jQuery("#weql_p_"+id).css("color","grey");
          jQuery("#weql_p_"+id).css("text-decoration","line-through");
          jQuery("#weql_containingspan").append(jQuery("#weql_p_"+id));
        }
      }
      <?php
    }
    $containingspan=new htmlElement("span",["id"=>"weql_containingspan"]);
    while($containingspan->toggle())
    {
      $completeitems="";
      foreach($items as $id)
      {
        $completed=$wpdb->get_var("SELECT completed FROM ${table_name} WHERE id=${id}");
        $data=["id"=>"weql_p_${id}"];
        if($completed)
        {
          $data["style"]="color:grey; text-decoration:line-through";
          ob_start();
        }
        $p=new htmlElement("p",$data);
        while($p->toggle())
        {
          $chidden=new htmlElement("input",["type"=>"hidden","id"=>"weql_curl_${id}","value"=>wp_nonce_url(get_site_url()."/wp-content/plugins/weql-supporters/weql-supporters.php?action=complete&id=${id}","complete_${id}")]);
          while($chidden->toggle()){}
          $uhidden=new htmlElement("input",["type"=>"hidden","id"=>"weql_uurl_${id}","value"=>wp_nonce_url(get_site_url()."/wp-content/plugins/weql-supporters/weql-supporters.php?action=uncomplete&id=${id}","uncomplete_${id}")]);
          while($uhidden->toggle()){}
          $ndata=["type"=>"checkbox","onclick"=>"weql_dashboard_toggle(${id})","id"=>"weql_checkbox_${id}"];
          if($completed)
          {
            $ndata["checked"]="checked";
          }
          $checkbox=new htmlElement("input",$ndata);
          while($checkbox->toggle()){}
          echo esc_html($wpdb->get_var("SELECT description FROM ${table_name} WHERE id=${id}"));
        }
        if($completed)
        {
          $completeitems.=ob_get_clean();
        }
      }
      echo $completeitems;
    }
  }
}

function weql_install()
{
  global $wpdb;
  $table1_name=$wpdb->prefix."weql_donors";
  $table2_name=$wpdb->prefix."weql_actionitems";

  $charset_collate = $wpdb->get_charset_collate();
  add_option( 'weql_version', WEQL_VERSION );
  require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

  $t1sql = "CREATE TABLE $table1_name (
  id mediumint(5) NOT NULL AUTO_INCREMENT,
  time datetime NOT NULL,
  email tinytext NOT NULL,
  name tinytext,
  nonce tinytext NOT NULL,
  amount mediumint(5) NOT NULL,
  basicreward mediumint(7),
  bronzereward mediumint(7),
  silverreward mediumint(7),
  goldreward mediumint(7),
  platinumreward mediumint(7),
  legendaryreward mediumint(7),
  UNIQUE KEY id (id)
) $charset_collate;";

  $t2sql="CREATE TABLE $table2_name (
  id mediumint(7) NOT NULL AUTO_INCREMENT,
  assignee tinyint,
  name tinytext NOT NULL,
  description text NOT NULL,
  added datetime NOT NULL,
  completed datetime,
  UNIQUE KEY id (id)
) $charset_collate;";

  dbDelta( $t1sql );
  dbDelta( $t2sql );

}
register_activation_hook( __FILE__, 'weql_install' );

function weql_add_donor($email,$amount)
{
  // NOTE: Amount in cents
  global $wpdb;
  $table_name=$wpdb->prefix."weql_donors";
  $data=["time"=>current_time('mysql'), "email" => $email, "amount" => $amount, "nonce"=>wp_generate_password(12,false,false)];
  $wpdb->insert($table_name,$data);
  if($amount>=10000)
  {
    $tier="legendary";
  }
  elseif($amount>=50000)
  {
    $tier="platinum";
  }
  elseif($amount>=25000)
  {
    $tier="gold";
  }
  elseif($amount>=15000)
  {
    $tier="silver";
  }
  elseif($amount>=500)
  {
    $tier="bronze";
  }
  else
  {
    $tier="basic";
  }
  $subject="Donation to WEQL Griffins";
  ob_start();
  $p=new htmlElement("p");
  while($p->toggle())
  {
    setlocale(LC_MONETARY, 'en_US.UTF-8');
    echo "Hello there!  It appears that you've recently made a donation to the WEQL Griffins Community Quidditch Team in the amount of ".money_format('%.2n', $amount/100) . ".  This qualifies you for the $tier tier of rewards, which are:";
  }
  $ul=new htmlElement("ul");
  while($ul->toggle())
  {
    $li=new htmlElement("li");
    if($amount>=10000)
    {
      while($li->toggle())
      {
        echo "Collaborate with the co-captains to design a play, named in your honor";
      }
    }
    if($amount>=5000)
    {
      while($li->toggle())
      {
        echo "Choose the jersey number for a WEQL Griffins player (limited number available)";
      }
    }
    if($amount>=2500)
    {
      while($li->toggle())
      {
        echo "Video thanking you at our next practice";
      }
    }
    if($amount>=1500)
    {
      while($li->toggle())
      {
        echo "Shout-out from our blog and Facebook page";
      }
    }
    if($amount>=500)
    {
      while($li->toggle())
      {
        echo "Listing on our Supporters page as a $tier-tier donor";
      }
    }
    while($li->toggle())
    {
      echo "A virtual hug in a thank-you email";
    }
  }
  while($p->toggle())
  {
    if($amount>=500)
    {
      echo "In order to complete these rewards and ensure that the donation was successful, we need to know your name.  It doesn't have to be your real name if you don't want, but this will be how we list you on our Supporters page and identify you for the rest of the rewards.  Please ";
    }
    else
    {
      echo "We'd like to ensure that there were no problems with the donation so that we can send you the virtual hug.  Please ";
    }
    $link="http://weqlgriffins.tk/register-donation?nonce=".$data["nonce"]."&id=".$wpdb->insert_id;
    $a=new htmlElement("a",["href"=>$link]);
    while($a->toggle())
    {
      echo "click here";
    }
    echo " (or, if that link doesn't work, go to this address: $link)";
    if($amount>=500)
    {
      echo " to submit your name so that the rewards can begin to process.";
    }
    else
    {
      echo " to confirm your donation.";
    }
  }
  while($p->toggle())
  {
    echo "If you have any questions, please feel free to reply to this email.  One of the team's co-captains will respond to you as quickly as possible - hopefully as soon as 24 hours.";
  }
  $body=ob_get_clean();
  $headers=["Content-Type: text/html; charset=UTF-8"];
  wp_mail($email,$subject,$body,$headers);
}

function weql_register_donor($id,$name,$nonce)
{
  global $wpdb;
  $table_name=$wpdb->prefix."weql_donors";
  $desired_nonce=$wpdb->get_var("SELECT nonce FROM $table_name WHERE id=$id");
  $amount=$wpdb->get_var("SELECT amount FROM $table_name WHERE id=$id");
  if($nonce!==$desired_nonce)
  {
    return false;
  }
  $data=["name"=>$name];
  foreach([100=>"basic",500=>"bronze",1500=>"silver",2500=>"gold",5000=>"platinum",10000=>"legendary"] as $damount=>$rewardtype)
  {
    if($amount>$damount)
    {
      $urewardtype=strtoupper($rewardtype);
      $furewardtype=ucfirst($rewardtype);
      $data["${rewardtype}reward"]=weql_create_actionitem("DONOR_${id}_${urewardtype}","${furewardtype} reward for ${name}",($damount<2000)?0:1);
    }
  }
  $wpdb->update($table_name,$data,["id"=>$id]);
}

function weql_create_actionitem($name,$desc,$assign=null)
{
  global $wpdb;
  $table_name=$wpdb->prefix."weql_actionitems";
  $data=["name"=>$name,"description"=>$desc,"added"=>current_time('mysql')];
  if($assign!==null)
  {
    $data["assignee"]=$assign;
  }
  $wpdb->insert($table_name,$data);
  return $wpdb->insert_id;
}

function weql_complete_actionitem($id)
{
  global $wpdb;
  $table_name=$wpdb->prefix."weql_actionitems";
  $wpdb->update($table_name,["completed"=>current_time('mysql')],["id"=>$id]);
}

function weql_uncomplete_actionitem($id)
{
  global $wpdb;
  $table_name=$wpdb->prefix."weql_actionitems";
  add_filter( 'query', 'wpse_143405_query' ); // See http://wordpress.stackexchange.com/questions/143405/wpdb-wont-insert-null-into-table-column
  $wpdb->update($table_name,["completed"=>'null'],["id"=>$id]);
  remove_filter( 'query', 'wpse_143405_query' );
}

function wpse_143405_query( $query )
{
    return str_ireplace( "'NULL'", "NULL", $query );
}
