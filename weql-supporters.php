<?php
/*
Plugin Name: WEQL Plugin
Plugin URI: https://github.com/iggyvolz/WEQL-Supporters-Plugin
Description: Plugin for the WEQL Griffins site to show supporters directly through PayPal
Version: 0.1
Author: iggyvolz
Author URI: https://iggyvolz.github.io
*/
define("WEQL_VERSION","0.1");
add_shortcode("weql-supporters", "weql_display_supporters");
add_action( 'wp_dashboard_setup', 'weql_add_actionitems_widget' );
function weql_add_actionitems_widget()
{
  wp_add_dashboard_widget("weql-action-items","Action Items","weql_display_action_items");
}

function weql_display_supporters()
{
  global $wpdb;
  $table_name=$wpdb->prefix."weql_donors";
  require_once("htmlElement/htmlElement.php");
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
    foreach($items as $id)
    {
      $p=new htmlElement("p");
      while($p->toggle())
      {
        echo wp_specialchars($wpdb->get_var("SELECT description FROM ${table_name} WHERE id=${id}"));
      }
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
  return ["id"=>$wpdb->insert_id,"nonce"=>$data["nonce"]];
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
  $wpdb->update($table_name,["completed"=>null],["id"=>$id]);
}
