<?php
/**
 * Plugin Name: Ninja Table Voting
 * Plugin URI: http://www.tjthouhid.com
 * Description: Ninja Table Voting
 * Version: 1.0.0
 * Author: Tj Thouhid
 * Author URI: http://www.facebook.com/tjthouhid
 * Text Domain: Optional. Plugin's text domain for localization. Example: mytextdomain
 * Domain Path: Optional. Plugin's relative directory path to .mo files. Example: /locale/
 * Network: Optional. Whether the plugin can only be activated network wide. Example: true
 * License: GPL2
 */

add_action("wp_ajax_update_vote_ninja_form", "update_vote_ninja_form");
add_action("wp_ajax_nopriv_update_vote_ninja_form", "update_vote_ninja_form_no_login");
function update_vote_ninja_form_no_login(){
    $json_array = array();
    $json_array['message'] = "You are not logged in.";
    $json_array['type'] = "error";
    $json_array = json_encode($json_array);
    echo $json_array;
    exit;
}
//require_once(dirname(dirname(__FILE__)).'/fluentform/app/Modules/Entries/Entries.php');
use FluentForm\App\Modules\Entries\Entries;
function update_vote_ninja_form(){
        global $wpdb;
        $formId =  1;
        $currentVote = $_REQUEST['currentVote'];
        $todaysvote = $_REQUEST['todaysvote'];
        $currentVote++;
        $todaysvote++;
        $entry_id = $_REQUEST['entry_id'];
        $user_id = get_current_user_id();
        $json_array = array();
        $table_name = "COIN_VOTE";
        $current_time = date("F j, Y \a\t g:ia");
        $sql = "SELECT *,   time_format(timediff('".current_time('mysql')."',votetime),'%H') as diffrence FROM `".$table_name."` WHERE `userid` = '".$user_id."' AND `coinid` = '".$entry_id."' ORDER BY votetime DESC  LIMIT 1";
        $result = $wpdb->get_results ($sql);
        if(count($result)>0){
            if($result[0]->diffrence<=12){
                $json_array['message'] = "You Already Voted this less then 12hour ago.";
                $json_array['type'] = "error";
                $json_array = json_encode($json_array);
                echo $json_array;
                exit;
            }
        }

        $wpdb->insert($table_name, array(
            'coinid' => $entry_id,
            'userid' => $user_id,
            'votetime' => current_time('mysql')
        ));
        // if($wpdb->last_error !== '') :
        //     $wpdb->print_error();
        // endif;

        $entry = wpFluent()->table('fluentform_submissions')
            ->where('id', $entry_id)
            ->first();

        if (!$entry) {
            throw new \Exception('No Entry Found');
        }

        $origianlResponse = json_decode($entry->response, true);
        //echo "<pre>"; print_r($origianlResponse);echo "</pre>";
        
        $origianlResponse['vote'] = $currentVote;
        $origianlResponse['todaysvotes'] = $todaysvote;
        //echo "<pre>"; print_r($origianlResponse);echo "</pre>";

        wpFluent()->table('fluentform_submissions')
        ->where('id', $entry_id)
        ->update([
            'response' => json_encode($origianlResponse),
            'updated_at' => current_time('mysql')
        ]);

        $diffs = [];
        $diffs['vote'] = $currentVote;
        $diffs['todaysvotes'] = $todaysvote;
        //echo "<pre>"; print_r($diffs);echo "</pre>";
        $entries = new Entries();
        $entries->updateEntryDiffs($entry_id, $formId, $diffs);
        $json_array['todaysvote'] = $todaysvote;
        $json_array['currentVote'] = $currentVote;
        $json_array['type'] = "success";

        $json_array = json_encode($json_array);
        echo $json_array;
        exit;
}

function vote_buttone_script() {
?>
<style type="text/css">
    .do-vote-btn{
        background-color: #fff;
        color: red !important;
        padding: 14px 25px !important;
        box-sizing: border-box !important;
        width: 100px;
        display: block;
        height: 30px;
        margin: 0px auto !important;
        text-align: center;
    }
    .do-vote-btn:hover{
        background-color: #dcd9d9;
    }
    .do-vote-btn.done-vote{
        background-color: #afa6a6;
        color: #decbcb !important;
    }
</style>
<script type="text/javascript">
  if ( undefined !== window.jQuery ) {
    // script dependent on jQuery
  }
  var ajaxurl = "<?php echo admin_url( 'admin-ajax.php' );?>";
  jQuery(function($){
        $("body").on("click",".do-vote-btn",function(e){
            e.preventDefault();
            $this = $(this);
            if($this.hasClass('done-vote')){
                return false;
            }
            id = $this.data("id");
            currentVote = $this.data("currentVote");
            todaysvote = $this.data("todaysvote");
            if(currentVote == ""){
                currentVote = 0;
            }
            if(todaysvote == ""){
                todaysvote = 0;
            }
            //console.log(id)
            //console.log(currentVote)
            jQuery.ajax({
                type : "post",
                dataType : "json",
                url : ajaxurl,
                data : {
                    action: "update_vote_ninja_form", 
                    currentVote : currentVote, 
                    todaysvote : todaysvote, 
                    entry_id : id, 
                },
                success: function(response) {
                    console.log(response)
                    if(response.type == "success") {
                        $this.closest("tr").find("td.ninja_clmn_nm_vote").text(response.currentVote);
                        $this.closest("tr").find("td.ninja_clmn_nm_todaysvotes").text(response.todaysvote);
                        $this.text("Voted");
                        $this.addClass("done-vote");
                        $this.attr("data-current-vote",response.currentVote);
                        $this.attr("data-todaysvote",response.todaysvote);
                    }else {
                        alert(response.message)
                    }
                }
            })   
        });
  })
</script>
<?php
}
add_action( 'wp_footer', 'vote_buttone_script' );


/*
* Con Event for clear todays vote daily
* Author : Tj Thouhid
* Date : 07-29-2021 
* 
 */
function clear_todays_vote(){
    require_once(dirname(dirname(__FILE__)).'/fluentform/app/Services/wpfluent/wpfluent.php');
    global $wpdb;
    $formId =  1;
    $entries = wpFluent()->table('fluentform_submissions')->where('form_id', $formId)->get();
    
    foreach ($entries as $key => $entry) {
        $origianlResponse = json_decode($entry->response, true);
        $origianlResponse['todaysvotes'] = 0;
        wpFluent()->table('fluentform_submissions')
        ->where('id', $entry->id)
        ->update([
            'response' => json_encode($origianlResponse),
            'updated_at' => current_time('mysql')
        ]);
    }
    $table_name = $wpdb->prefix . "fluentform_entry_details";
    $wpdb->update($table_name, array(
        'field_value' => 0
    ), array(
        'form_id'=>$formId,
        'field_name'=>'todaysvotes'
    ));
}
//clear_todays_vote();
add_action( 'clear_todays_vote_corn_event', 'clear_todays_vote' );