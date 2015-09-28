<?php
/*
Plugin Name: WEQL Plugin
Plugin URI: https://github.com/iggyvolz/WEQL-Supporters-Plugin
Description: Plugin for the WEQL Griffins site to show supporters directly through PayPal
Version: 0.21
Author: iggyvolz
Author URI: https://iggyvolz.github.io
*/
require_once("vendor/autoload.php");
use htmlElement\htmlElement;
define("WEQL_VERSION","0.21");
define("WEQL_TESTING",true);
setlocale(LC_MONETARY, 'en_US.UTF-8');
if(!function_exists("add_action"))
{
  require_once(dirname(dirname(dirname(__DIR__)))."/wp-config.php");
  $wp->init();
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
        if(!weql_register_donor($_GET["id"],$_GET["name"],$_GET["nonce"]))
        {
          http_response_code(500);
        }
      case "simulate":
        weql_add_donor($_GET["email"],$_GET["amount"]+0);
    endswitch;
  }
}
add_shortcode("weql-supporters", "weql_display_supporters");
add_shortcode("weql-register", "weql_display_register");
add_shortcode("weql-simulate", "weql_display_simulate");
add_shortcode("weql-donationamount", "weql_display_donationamount");
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
  foreach(["bronze"=>[500,1499],"silver"=>[1500,2499],"gold"=>[2500,4999],"platinum"=>[5000,9999],"legendary"=>[10000,99999]] as $type=>$amounts)
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
  return "<script>function getUrlVars() {var vars = {};var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {vars[key] = value;});return vars;}jQuery(\"#weql_donation_submit\").click(function(){var name=encodeURIComponent (jQuery(\"#weql_donation_name\").val());jQuery(\"#weql_containingspan\").html(\"Processing...\");jQuery.get(\"http://weqlgriffins.tk/wp-content/plugins/weql-supporters/weql-supporters.php?action=register&id=\"+getUrlVars()[\"id\"]+\"&nonce=\"+getUrlVars()[\"nonce\"]+\"&name=\"+name).done(function(){jQuery(\"#weql_containingspan\").html(\"Complete!  Expect a follow-up email shortly.\")}).fail(function(){jQuery(\"#weql_containingspan\").html(\"Uh-oh!  Something went wrong.  Try refreshing the page and trying again, or respond to our email with your name so we can put it in manually.\")})});</script>";
}
function weql_display_simulate()
{
  return "<script>jQuery(\"#weql_donation_submit\").click(function(){var n=encodeURIComponent(jQuery(\"#weql_donation_email\").val()),e=encodeURIComponent(jQuery(\"#weql_donation_amount\").val());jQuery(\"#weql_containingspan\").html(\"Processing...\"),jQuery.get(\"http://weqlgriffins.tk/wp-content/plugins/weql-supporters/weql-supporters.php?action=simulate&email=\"+n+\"&amount=\"+e).done(function(){jQuery(\"#weql_containingspan\").html(\"Complete!  Check your email.\")}).fail(function(){jQuery(\"#weql_containingspan\").html(\"Uh-oh!  Something went wrong.  Try refreshing the page and trying again, or respond to our email with your name so we can put it in manually.\")})});</script>";
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
  if(WEQL_TESTING)
  {
    while($p->toggle())
    {
      echo "NOTICE - Testing mode is enabled.  You have made no donation and this is just testing the donation-handling system.";
    }
  }
  while($p->toggle())
  {
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
  $id=$id+0; // Force ID to number
  $table_name=$wpdb->prefix."weql_donors";
  $desired_nonce=$wpdb->get_var("SELECT nonce FROM $table_name WHERE id=$id");
  $amount=$wpdb->get_var("SELECT amount FROM $table_name WHERE id=$id");
  $email=$wpdb->get_var("SELECT email FROM $table_name WHERE id=$id");
  if($nonce!==$desired_nonce)
  {
    return false;
  }
  $dname=$wpdb->get_var("SELECT name FROM $table_name WHERE id=$id");
  if($dname!==NULL)
  {
    return false;
  }
  $total=get_option('weql_total',0);
  $total+=$amount;
  add_option('weql_total', $total );
  $data=["name"=>$name];
  foreach([2500=>"gold",5000=>"platinum",10000=>"legendary"] as $damount=>$rewardtype)
  {
    if($amount>$damount)
    {
      $urewardtype=strtoupper($rewardtype);
      $furewardtype=ucfirst($rewardtype);
      $data["${rewardtype}reward"]=weql_create_actionitem("DONOR_${id}_${urewardtype}","${furewardtype} reward for ${name}",1);
    }
  }
  $wpdb->update($table_name,$data,["id"=>$id]);
  $subject="Re: Donation to WEQL Griffins";
  ob_start();
  $p=new htmlElement("p");
  while($p->toggle())
  {
    echo "Dear ${name},";
  }
  while($p->toggle())
  {
    echo "Thank you so much for your contribution!  Your donation has been processed, and we cannot thank you enough!  We couldn't exist as a team without you.";
  }
  while($p->toggle())
  {
    echo "Your donation now brings us to ".money_format('%.2n', $total/100) . ", out of our $600 goal!";
  }
  while($p->toggle())
  {
    echo "Now comes the part you have been waiting for - your rewards:";
  }
  while($p->toggle())
  {
    echo "Please click here to get your virtual hug (TODO - make video of virtual hug).";
  }
  if($amount>500)
  {
    while($p->toggle())
    {
      echo "You have been added to the ";
      $a=new htmlElement("a",["href"=>"http://weqlgriffins.tk/supporters/"]);
      while($a->toggle())
      {
        echo "Supporters page";
      }
      echo " on our website!";
    }
  }
  if($amount>1500)
  {
    $sname=esc_html($name);
    $post_id=wp_insert_post(["post_title"=>"Donation from ${sname}","post_name"=>"${sname}_donation","post_content"=>"A huge thank-you to ${sname} for their donation of ".money_format('%.2n', $amount/100) . "!","post_excerpt"=>"A huge thank-you to ${sname} for their donation of ".money_format('%.2n', $amount/100) . "!","post_status"=>"publish","post_author"=>1]);
    $lemail="trigger@recipe.ifttt.com";
    $lsubject="#facebook";
    $lbody="A huge thank-you to ${sname} for their donation of ".money_format('%.2n', $amount/100) . "!";
    $headers=["Content-Type: text/html; charset=UTF-8"];
    wp_mail($lemail,$lsubject,$lbody,$headers);
    while($p->toggle())
    {
      echo "You've been given a shout-out on our ";
      $link=new htmlElement("a",["href"=>"http://weqlgriffins.tk/?p=".$post_id]);
      while($link->toggle())
      {
        echo "blog";
      }
      echo ", and one is processing on our ";
      $link=new htmlElement("a",["href"=>"https://www.facebook.com/pages/WEQL-Griffins/263934986996139"]);
      while($link->toggle())
      {
        echo "Facebook page.";
      }
    }
  }
  if($amount>2500)
  {
    while($p->toggle())
    {
      echo "We have scheduled the video shout-out, but someone from the team may email you to ensure we are pronouncing your name correctly.";
    }
  }
  if($amount>5000)
  {
    while($p->toggle())
    {
      echo "Someone from the team will contact you on how to pick a jersey number.";
    }
  }
  if($amount>10000)
  {
    while($p->toggle())
    {
      echo "We will also get in touch with you about designing a play.";
    }
  }
  while($p->toggle())
  {
    echo "Thank you again for your continued support.  Without people like you backing us, we will not be able to field a team to play Quidditch this season.";
  }
  $body=ob_get_clean();
  $headers=["Content-Type: text/html; charset=UTF-8"];
  wp_mail($email,$subject,$body,$headers);
  return true;
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
function weql_display_donationamount()
{
  echo money_format('%.2n', get_option('weql_total',0)/100);
}
