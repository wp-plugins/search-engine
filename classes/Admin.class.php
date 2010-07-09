<?php
global $wpdb;
if(!is_object($wpdb))
{
    ob_start();
    if(file_exists(realpath('../../../../wp-load.php')))
    {
        require_once(realpath('../../../../wp-load.php'));
    }
    else
    {
        require_once(realpath('../../../wp-load.php'));
    }
    ob_end_clean();
}
// FOR EXPORTS ONLY
if(isset($_GET['download'])&&!isset($_GET['page'])&&is_user_logged_in())
{
    do_action('wp_admin_ui_export_download');
    $file = WP_CONTENT_DIR.'/exports/'.str_replace('/','',$_GET['export']);
    if(!isset($_GET['export'])||empty($_GET['export'])||!file_exists($file))
    {
        die('File not found.');
    }
    // required for IE, otherwise Content-disposition is ignored
    if(ini_get('zlib.output_compression'))
        ini_set('zlib.output_compression','Off');
    header("Pragma: public"); // required
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private",false); // required for certain browsers
    header("Content-Type: application/force-download");
    header("Content-Disposition: attachment; filename=\"".basename($file)."\";" );
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: ".filesize($file));
    readfile("$file");
    exit();
}
/**
 * Admin UI class for WordPress plugins
 *
 * Creates a UI for any plugn screens within WordPress
 *
 * NOTE: If you are including this class in a plugin,
 * consider renaming the class to avoid conflicts with other plugins.
 * This is not required, but some developers may include the class
 * the wrong way which could cause an issue with your including the
 * class file.
 *
 * @package Admin UI for Plugins
 *
 * @version 1.3.0
 * @author Scott Kingsley Clark
 * @link http://www.scottkclark.com/
 *
 * @param mixed $options
 */
class WP_Admin_UI
{
    // base
    var $item = 'Item';
    var $items = 'Items';
    var $heading = array('manage'=>'Manage');
    var $table = false;
    var $icon = false;
    var $css = false;

    // actions
    var $add = true;
    var $view = false;
    var $edit = true;
    var $delete = true;
    var $save = true;
    var $readonly = false;
    var $export = false;

    // array of custom functions to run for actions / etc
    var $custom = array();

    // data related
    var $total = 0;
    var $columns = array();
    var $data = array();
    var $full_data = array();
    var $row = array();
    var $order_columns = array();
    var $search_columns = array();
    var $form_columns = array();
    var $view_columns = array();
    var $insert_id = 0;

    // other options
    var $id = false;
    var $action = 'manage';
    var $do = false;
    var $search = true;
    var $search_query = false;
    var $pagination = true;
    var $page = 1;
    var $limit = 25;
    var $order = 'id';
    var $order_dir = 'DESC';
    var $sql = false;

    function __construct ($options=false)
    {
        do_action('wp_admin_ui_pre_init',$options);
        $options = $this->do_hook('options',$options);
        $this->export_dir = WP_CONTENT_DIR.'/exports';
        $this->export_url = WP_CONTENT_URL.(str_replace(WP_CONTENT_DIR,'',__FILE__)).'?download=1&export=';
        if(false!==$this->get_var('id'))
            $this->id = $_GET['id'];
        if(false!==$this->get_var('action',false,array('add','edit','view','delete','manage','export')))
            $this->action = $_GET['action'];
        if(false!==$this->get_var('do',false,array('save','create')))
            $this->do = $_GET['do'];
        if(false!==$this->get_var('search_query'))
            $this->search_query = $_GET['search_query'];
        if(false!==$this->get_var('pg'))
            $this->page = $_GET['pg'];
        if(false!==$this->get_var('limit'))
            $this->limit = $_GET['limit'];
        if(false!==$this->get_var('order'))
            $this->order = $_GET['order'];
        if(false!==$this->get_var('order_dir',false,array('ASC','DESC')))
            $this->order_dir = $_GET['order_dir'];
        if(false!==$this->get_var('action',false,'export')&&false!==$this->get_var('export_type',false,array('csv','tab','xml','json')))
            $this->export_type = $_GET['export_type'];
        if(false!==$options&&!empty($options))
        {
            if(!is_array($options))
                parse_str($options,$options);
            foreach($options as $option=>$value)
                $this->$option = $value;
        }
        if(false!==$this->readonly)
            $this->add = $this->edit = $this->delete = $this->save = false;
        $this->columns = $this->setup_columns();
        $this->do_hook('post_init',$options);
    }
    function get_var ($index,$default=false,$allowed=false,$array=false)
    {
        if(!is_array($array))
        {
            if($array=='post')
                $array = $_POST;
            else
                $array = $_GET;
        }
        if(false!==$allowed&&!is_array($allowed))
            $allowed = array($allowed);
        $value = $default;
        if(isset($array[$index])&&(false===$allowed||in_array($array[$index],$allowed)))
            $value = $array[$index];
        return $this->do_hook('get_var',$value,$index,$default,$allowed,$array);
    }
    function hidden_vars ()
    {
        $this->do_hook('hidden_vars');
        foreach($_GET as $k=>$v)
        {
?>
<input type="hidden" name="<?php echo $k; ?>" value="<?php echo $v; ?>" />
<?php
        }
    }
    /*
    // Example code for use with $this->do_hook
    function my_filter_function ($args,$obj)
    {
        $obj[0]->item = 'Post';
        $obj[0]->add = true;
        // args are an array (0=>$arg1,1=>$arg2)
        // may have more than one arg, dependant on filter
        return $args;
    }
    add_filter('wp_admin_ui_post_init','my_filter_function',10,2);
    // OR
    add_action('wp_admin_ui_post_init','my_filter_function',10,2);
    */
    function do_hook ()
    {
        $args = func_get_args();
        if(empty($args))
            return false;
        $filter = $args[0];
        unset($args[0]);
        $args = apply_filters('wp_admin_ui_'.$filter,$args,array(&$this,'wp_admin_ui_'.$filter));
        if(isset($args[1]))
            return $args[1];
        return false;
    }
    function var_update($array=false,$allowed=false,$url=false)
    {
        $excluded = array('do','id','pg','search_query','order','order_dir','limit','action','export','export_type','remove_export','updated');
        if(false===$allowed)
            $allowed = array();
        if(!isset($_GET))
            $get = array();
        else
            $get = $_GET;
        if(is_array($array))
        {
            foreach($excluded as $exclusion)
                if(!isset($array[$exclusion])&&!in_array($exclusion,$allowed))
                    unset($get[$exclusion]);
            foreach($array as $key=>$val)
            {
                if(0<strlen($val))
                    $get[$key] = $val;
                else
                    unset($get[$key]);
            }
        }
        if(false===$url)
            $url = '';
        else
        {
            $url = explode('?',$_SERVER['REQUEST_URI']);
            $url = explode('#',$url[0]);
            $url = $url[0];
        }
        return $this->do_hook('var_update',$url.'?'.http_build_query($get));
    }
    function sanitize ($input)
    {
        global $wpdb;
        $output = array();
        if(is_object($input))
        {
            $input = (array) $input;
            foreach($input as $key => $val)
            {
                $output[$key] = $this->sanitize($val);
            }
            $output = (object) $output;
        }
        elseif(is_array($input))
        {
            foreach($input as $key => $val)
            {
                $output[$key] = $this->sanitize($val);
            }
        }
        elseif(empty($input))
        {
            $output = $input;
        }
        else
        {
            $output = $wpdb->_real_escape(trim($input));
        }
        return $output;
    }
    function unsanitize ($input)
    {
        $output = array();
        if(is_object($input))
        {
            $input = (array) $input;
            foreach($input as $key => $val)
            {
                $output[$key] = $this->unsanitize($val);
            }
            $output = (object) $output;
        }
        elseif(is_array($input))
        {
            foreach($input as $key => $val)
            {
                $output[$key] = $this->unsanitize($val);
            }
        }
        elseif(empty($input))
        {
            $output = $input;
        }
        else
        {
            $output = stripslashes($input);
        }
        return $output;
    }
    function setup_columns ($columns=null)
    {
        $init = false;
        if(null===$columns)
        {
            $columns = $this->columns;
            $init = true;
        }
        if(!empty($columns))
        {
            // Available Attributes
            // type = field type
                // type = date (data validation as datetime)
                    // date_touch = use current timestamp when saving (even if readonly, if type=date)
                    // date_touch_on_create = use current timestamp when saving ONLY on create (even if readonly, if type=date)
                // type = text / other (single line text box)
                // type = desc (textarea)
                // type = number (data validation as int float)
                // type = decimal (data validation as decimal)
                // type = password (single line password box)
                // type = bool (single line password box)
                // type = related (select box)
                    // related = table to relate to (if type=related)
                    // related_field = field name on table to show (if type=related) - default "name"
                    // related_multiple = true (ability to select multiple values if type=related)
                    // related_sql = custom where / order by SQL (if type=related)
            // readonly = true (shows as text)
            // display = false (doesn't show on form, but can be saved)
            // comments = comments to show for field
            // comments_top = true (shows comments above field instead of below)
            $new_columns = array();
            foreach($columns as $column=>$attributes)
            {
                if(!is_array($attributes))
                {
                    $column = $attributes;
                    $attributes = array();
                }
                if(!isset($attributes['label']))
                    $attributes['label'] = ucwords(str_replace('_',' ',$column));
                if(!isset($attributes['type']))
                    $attributes['type'] = 'text';
                if(!isset($attributes['related']))
                    $attributes['related'] = false;
                if(!isset($attributes['related_field']))
                    $attributes['related_field'] = 'name';
                if(!isset($attributes['related_multiple']))
                    $attributes['related_multiple'] = false;
                if(!isset($attributes['related_sql']))
                    $attributes['related_sql'] = false;
                if(!isset($attributes['readonly']))
                    $attributes['readonly'] = false;
                if(!isset($attributes['date_touch'])||$attributes['type']!='date')
                    $attributes['date_touch'] = false;
                if(!isset($attributes['date_touch_on_create'])||$attributes['type']!='date')
                    $attributes['date_touch_on_create'] = false;
                if(!isset($attributes['display']))
                    $attributes['display'] = true;
                if(!isset($attributes['export']))
                    $attributes['export'] = true;
                if(!isset($attributes['comments']))
                    $attributes['comments'] = '';
                if(!isset($attributes['comments_top']))
                    $attributes['comments_top'] = false;
                if(!isset($attributes['custom_view']))
                    $attributes['custom_view'] = false;
                if(!isset($attributes['custom_input']))
                    $attributes['custom_input'] = false;
                if(!isset($attributes['custom_display']))
                    $attributes['custom_display'] = false;
                if(!isset($attributes['custom_form_display']))
                    $attributes['custom_form_display'] = false;
                $new_columns[$column] = $attributes;
            }
            $columns = $new_columns;
        }
        if(false!==$init)
        {
            if(!empty($this->form_columns))
                $this->form_columns = $this->setup_columns($this->form_columns);
            else
                $this->form_columns = $columns;
            if(!empty($this->search_columns))
                $this->search_columns = $this->setup_columns($this->search_columns);
            else
                $this->search_columns = $columns;
            if(!empty($this->export_columns))
                $this->export_columns = $this->setup_columns($this->export_columns);
            else
                $this->export_columns = $columns;
        }
        return $columns;
    }
    function message ($msg)
    {
        $msg = $this->do_hook('message',$msg);
?>
	<div id="message" class="updated fade"><p><?php echo $msg; ?></p></div>
<?php
    }
    function error ($msg)
    {
        $msg = $this->do_hook('error',$msg);
?>
	<div id="message" class="error fade"><p><?php echo $msg; ?></p></div>
<?php
    }
    function go ()
    {
        $this->do_hook('go');
        $_GET = $this->unsanitize($_GET);
        $_POST = $this->unsanitize($_POST);
        if(false!==$this->css)
        {
?>
    <link  type="text/css" rel="stylesheet" href="<?php echo $this->css; ?>" />
<?php
        }
        if(isset($this->custom[$this->action])&&function_exists("{$this->custom[$this->action]}"))
            $this->custom[$this->action]($this);
        elseif($this->action=='add'&&$this->add)
        {
            if($this->do=='create'&&$this->save)
            {
                $this->save(1);
                $this->manage();
            }
            else
                $this->add();
        }
        elseif($this->action=='edit'&&$this->edit)
        {
            if($this->do=='save'&&$this->save)
            {
                $this->save();
            }
            $this->edit();
        }
        elseif($this->action=='delete'&&$this->delete)
        {
            $this->delete();
            $this->manage();
        }
        elseif($this->do=='save'&&$this->save)
        {
            $this->save();
            $this->manage();
        }
        elseif($this->do=='create'&&$this->save)
        {
            $this->save(1);
            $this->manage();
        }
        elseif($this->action=='view'&&$this->view)
            $this->view();
        else
            $this->manage();
    }
    function add ()
    {
        $this->do_hook('add');
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32"<?php if(false!==$this->icon){ ?> style="background-position:0 0;background-image:url(<?php echo $this->icon; ?>);"<?php } ?>><br /></div>
    <h2>Add New <?php echo $this->item; ?> <small>(<a href="<?php echo $this->var_update(array('action'=>'manage','id'=>'')); ?>">&laquo; Back to Manage</a>)</small></h2>
<?php $this->form(1); ?>
</div>
<?php
    }
    function edit ()
    {
        $this->do_hook('edit');
        if(isset($this->custom['edit'])&&function_exists("{$this->custom['edit']}"))
            $this->custom['edit']($this);
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32"<?php if(false!==$this->icon){ ?> style="background-position:0 0;background-image:url(<?php echo $this->icon; ?>);"<?php } ?>><br /></div>
    <h2>Edit <?php echo $this->item; ?> <small>(<a href="<?php echo $this->var_update(array('action'=>'manage','id'=>'')); ?>">&laquo; Back to Manage</a>)</small></h2>
<?php $this->form(); ?>
</div>
<?php
    }
    function form ($create=0)
    {
        $this->do_hook('form',$create);
        if(isset($this->custom['form'])&&function_exists("{$this->custom['form']}"))
            return $this->custom['form']($this,$create);
        if(false===$this->table&&false===$this->sql)
            return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        global $wpdb;
        if(empty($this->form_columns))
            $this->form_columns = $this->columns;
        $submit = 'Add '.$this->item;
        $id = '';
        $vars = array('action'=>'manage','do'=>'create','id'=>$id);
        if($create==0)
        {
            if(empty($this->row))
                $this->get_row();
            if(empty($this->row))
                return $this->error("<strong>Error:</strong> $this->item not found.");
            $submit = 'Save Changes';
            $id = $this->row['id'];
            $vars = array('action'=>'edit','do'=>'save','id'=>$id);
        }
?>
    <form method="post" action="<?php echo $this->var_update($vars); ?>" class="wp_admin_ui">
        <table class="form-table">
<?php
        foreach($this->form_columns as $column=>$attributes)
        {
            if(!isset($this->row[$column]))
                $this->row[$column] = '';
            if($attributes['type']=='bool')
                $selected = ($this->row[$column]==1?' CHECKED':'');
            if(false===$attributes['display'])
                continue;
?>
    <tr valign="top">
        <th scope="row"><label for="admin_ui_<?php echo $column; ?>"><?php echo $attributes['label']; ?></label></th>
        <td>
<?php
            if(!empty($attributes['comments'])&&!empty($attributes['comments_top']))
            {
?>
            <span class="description"><?php echo $attributes['comments']; ?></span>
<?php
                if($attributes['type']!='desc'||$attributes['type']!='code')
                    echo "<br />";
            }
            if(false!==$attributes['custom_input']&&function_exists("{$attributes['custom_input']}"))
            {
                $attributes['custom_input']($column,$attributes,$this);
?>
        </td>
    </tr>
<?php
                continue;
            }
            if(false!==$attributes['custom_form_display']&&function_exists("{$attributes['custom_form_display']}"))
            {
                $this->row[$column] = $attributes['custom_form_display']($column,$attributes,$this);
            }
            if(false!==$attributes['readonly'])
            {
?>
            <div id="admin_ui_<?php echo $column; ?>"><?php echo $this->row[$column]; ?></div>
<?php
            }
            else
            {
                if($attributes['type']=='bool')
                {
?>
            <input type="checkbox" name="<?php echo $column; ?>" id="admin_ui_<?php echo $column; ?>" value="1"<?php echo $selected; ?> />
<?php
                }
                elseif($attributes['type']=='password')
                {
?>
            <input type="password" name="<?php echo $column; ?>" id="admin_ui_<?php echo $column; ?>" value="<?php echo $this->row[$column]; ?>" class="regular-text" />
<?php
                }
                elseif($attributes['type']=='desc'||$attributes['type']=='code')
                {
?>
            <textarea name="<?php echo $column; ?>" id="admin_ui_<?php echo $column; ?>" rows="10" cols="50"><?php echo $this->row[$column]; ?></textarea>
<?php
                }
                elseif($attributes['type']=='related'&&false!==$attributes['related'])
                {
                    if(false===$attributes['related_custom'])
                    {
                        $related = $wpdb->get_results('SELECT id,`'.$attributes['related_field'].'` FROM '.$attributes['related'].(!empty($attributes['related_sql'])?' '.$attributes['related_sql']:''));
?>
            <select name="<?php echo $column; ?><?php echo (false!==$attributes['related_multiple']?'[]':''); ?>" id="admin_ui_<?php echo $column; ?>"<?php echo (false!==$attributes['related_multiple']?' MULTIPLE':''); ?>>
<?php
                        $selected_options = explode(',',$this->row[$column]);
                        foreach($related as $option)
                        {
?>
                <option value="<?php echo $option->id; ?>"<?php echo (in_array($option->id,$selected_options)?' SELECTED':''); ?>><?php echo $option->$attributes['related_field']; ?></option>
<?php
                        }
?>
            </select>
<?php
                    }
                    else
                    {
                        $related = $attributes['related_custom'];
                        if(!is_array($related))
                        {
                            $related = explode(',',$related);
                        }
?>
            <select name="<?php echo $column; ?><?php echo (false!==$attributes['related_multiple']?'[]':''); ?>" id="admin_ui_<?php echo $column; ?>"<?php echo (false!==$attributes['related_multiple']?' MULTIPLE':''); ?>>
<?php
                        $selected_options = explode(',',$this->row[$column]);
                        foreach($related as $option_id=>$option)
                        {
                            if(!is_array($option))
                            {
                                $option_id = $option;
                                $option = array();
                            }
                            if(!isset($option['label']))
                                $option['label'] = ucwords(str_replace('_',' ',$option_id));
?>
                <option value="<?php echo $option->id; ?>"<?php echo (in_array($option->id,$selected_options)?' SELECTED':''); ?>><?php echo $option->$attributes['related_field']; ?></option>
<?php
                        }
?>
            </select>
<?php
                    }
                }
                else
                {
?>
            <input type="text" name="<?php echo $column; ?>" id="admin_ui_<?php echo $column; ?>" value="<?php echo $this->row[$column]; ?>" class="regular-text" />
<?php
                }
            }
            if(!empty($attributes['comments'])&&false===$attributes['comments_top'])
            {
                if($attributes['type']!='desc'||$attributes['type']!='code')
                    echo "<br />";
?>
            <span class="description"><?php echo $attributes['comments']; ?></span>
<?php
            }
?>
        </td>
    </tr>
<?php
        }
?>
        </table>
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="<?php echo $submit; ?>" />
        </p>
    </form>
<?php
    }
    function view ()
    {
        $this->do_hook('view');
        if(isset($this->custom['view'])&&function_exists("{$this->custom['view']}"))
            return $this->custom['view']($this);
        if(false===$this->table&&false===$this->sql)
            return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        global $wpdb;
        if(empty($this->row))
            $this->get_row();
        if(empty($this->row))
            return $this->error("<strong>Error:</strong> $this->item not found.");
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32"<?php if(false!==$this->icon){ ?> style="background-position:0 0;background-image:url(<?php echo $this->icon; ?>);"<?php } ?>><br /></div>
    <h2>View <?php echo $this->item; ?> <small>(<a href="<?php echo $this->var_update(array('action'=>'manage','id'=>'')); ?>">&laquo; Back to Manage</a>)</small></h2>
    <table class="form-table">
<?php
        foreach($this->form_columns as $column=>$attributes)
        {
            if(!isset($this->row[$column]))
                $this->row[$column] = '';
            if($attributes['type']=='bool')
                $selected = ($this->row[$column]==1?' CHECKED':'');
            if(false===$attributes['display'])
                continue;
?>
        <tr valign="top">
            <th scope="row"><label for="admin_ui_<?php echo $column; ?>"><?php echo $attributes['label']; ?></label></th>
            <td>
<?php
            if(!empty($attributes['comments'])&&false!==$attributes['comments_top'])
            {
?>
            <span class="description"><?php echo $attributes['comments']; ?></span>
<?php
            }
            if(false!==$attributes['custom_view']&&function_exists("{$attributes['custom_view']}"))
            {
                $attributes['custom_view']($column,$attributes,$this);
?>
            </td>
        </tr>
<?php
                continue;
            }
            if($attributes['type']=='bool')
            {
                $this->row[$column] = ($this->row[$column]==1?'Yes':'No');
            }
            elseif($attributes['type']=='related')
            {
                $old_value = $this->row[$column];
                $this->row[$column] = '';
                if(!empty($old_value))
                {
                    $this->row[$column] = array();
                    $related = $wpdb->get_results('SELECT id,`'.$attributes['related_field'].'` FROM '.$attributes['related'].' WHERE id IN ('.$old_value.')'.(!empty($attributes['related_sql'])?' '.$attributes['related_sql']:''));
                    foreach($related as $option)
                    {
                        $this->row[$column][] = $option->$attributes['related_field'];
                    }
                    $this->row[$column] = '<ul><li>'.implode('</li><li>',$this->row[$column]).'</li></ul>';
                }
                else
                {
                    $this->row[$column] = 'N/A';
                }
            }
?>
            <div id="admin_ui_<?php echo $column; ?>"><?php echo $this->row[$column]; ?></div>
<?php
            if(!empty($attributes['comments'])&&false===$attributes['comments_top'])
            {
?>
            <span class="description"><?php echo $attributes['comments']; ?></span>
<?php
            }
?>
            </td>
        </tr>
<?php
        }
?>
    </table>
</div>
<?php
    }
    function delete ($id=false)
    {
        $this->do_hook('pre_delete');
        if(isset($this->custom['delete'])&&function_exists("{$this->custom['delete']}"))
            return $this->custom['delete']($this);
        if(false===$this->table&&false===$this->sql)
            return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        if(false===$this->id&&false===$id)
            return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "id" definition.');
        if(false===$id)
            $id = $this->id;
        global $wpdb;
        $check = $wpdb->query($wpdb->prepare("DELETE FROM $this->table WHERE `id`=%d",array($id)));
        if($check)
            $this->message("<strong>Deleted:</strong> $this->item has been deleted.");
        else
            $this->error("<strong>Error:</strong> $this->item has not been deleted.");
        $this->do_hook('post_delete',$id);
    }
    function save ($create=0)
    {
        $this->do_hook('pre_save',$create);
        if(isset($this->custom['save'])&&function_exists("{$this->custom['save']}"))
            return $this->custom['save']($this);
        global $wpdb;
        $action = 'saved';
        if($create==1)
            $action = 'created';
        $column_sql = array();
        $values = array();
        $data = array();
        foreach($this->form_columns as $column=>$attributes)
        {
            $vartype = '%s';
            if($attributes['type']=='bool')
                $selected = ($_POST[$column]==1?1:0);
            if(false===$attributes['display']||false!==$attributes['readonly'])
            {
                if($attributes['type']!='date')
                    continue;
                if(false===$attributes['date_touch']&&(false===$attributes['date_touch_on_create']||$create!=1||$this->id>0))
                    continue;
            }
            if($attributes['type']=='date')
            {
                if(false!==$attributes['date_touch']||(false!==$attributes['date_touch_on_create']&&$create==1&&$this->id<1))
                    $value = date("Y-m-d H:i:s");
                else
                    $value = date("Y-m-d H:i:s",strtotime($_POST[$column]));
            }
            else
            {
                if($attributes['type']=='bool')
                {
                    $vartype = '%d';
                    $value = 0;
                    if(isset($_POST[$column]))
                        $value = 1;
                }
                elseif($attributes['type']=='number')
                {
                    $vartype = '%d';
                    $value = number_format($_POST[$column],0,'','');
                }
                elseif($attributes['type']=='decimal')
                {
                    $vartype = '%d';
                    $value = number_format($_POST[$column],2,'.','');
                }
                elseif($attributes['type']=='related')
                {
                    if(is_array($_POST[$column]))
                        $value = implode(',',$_POST[$column]);
                    else
                        $value = $_POST[$column];
                }
                else
                    $value = $_POST[$column];
            }
            if(false!==$attributes['custom_save']&&function_exists("{$attributes['custom_save']}"))
                $value = $attributes['custom_save']($value,$column,$attributes,$this);
            $column_sql[] = "`$column`=$vartype";
            $values[] = $value;
            $data[$column] = $value;
        }
        $column_sql = implode(',',$column_sql);
        if($create==0&&$this->id>0)
        {
            $this->insert_id = $this->id;
            $values[] = $this->id;
            $check = $wpdb->query($wpdb->prepare("UPDATE $this->table SET $column_sql WHERE id=%d",$values));
        }
        else
            $check = $wpdb->query($wpdb->prepare("INSERT INTO $this->table SET $column_sql",$values));
        if($check)
        {
            if($this->insert_id==0)
                $this->insert_id = $wpdb->insert_id;
            $this->message('<strong>Success!</strong> '.$this->item.' '.$action.' successfully.');
        }
        else
            $this->error('<strong>Error</strong> '.$this->item.' has not been '.$action.'.');
        $this->do_hook('post_save',$this->insert_id,$data,$create);
    }
    function export ()
    {
        $this->do_hook('export');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $url = explode('/',$_SERVER['REQUEST_URI']);
        $url = array_reverse($url);
        $url = $url[0];
        if ( false === ($credentials = request_filesystem_credentials($url, '', false, ABSPATH)) )
        {
            $this->error("<strong>Error:</strong> Your hosting configuration does not allow access to add files to your site.");
            return false;
        }
        if ( ! WP_Filesystem($credentials, ABSPATH) ) {
            request_filesystem_credentials($url, '', true, ABSPATH); //Failed to connect, Error and request again
            $this->error("<strong>Error:</strong> Your hosting configuration does not allow access to add files to your site.");
            return false;
        }
        global $wp_filesystem;
        if(isset($this->custom['export'])&&function_exists("{$this->custom['export']}"))
            return $this->custom['export']($this);
        if(empty($this->full_data))
            $this->get_data(true);
        $dir = dirname($this->export_dir);
        if(!file_exists($this->export_dir))
        {
            if(!$wp_filesystem->is_writable($dir)||!($dir = $wp_filesystem->mkdir($this->export_dir)))
            {
                $this->error("<strong>Error:</strong> Your export directory (<strong>$this->export_dir</strong>) did not exist and couldn&#8217;t be created by the web server. Check the directory permissions and try again.");
                return false;
            }
        }
        if(!$wp_filesystem->is_writable($this->export_dir))
        {
            $this->error("<strong>Error:</strong> Your export directory (<strong>$this->export_dir</strong>) needs to be writable for this plugin to work. Double-check it and try again.");
            return false;
        }
        if(isset($_GET['remove_export']))
        {
            if($wp_filesystem->exists($this->export_dir.'/'.str_replace('/','',$_GET['remove_export'])))
            {
                $remove = @unlink($this->export_dir.'/'.str_replace('/','',$_GET['remove_export']));
                if($remove)
                {
                    $this->message('<strong>Success:</strong> Export removed successfully.');
                    return;
                }
                else
                {
                    $this->error("<strong>Error:</strong> Your export directory (<strong>$this->export_dir</strong>) needs to be writable for this plugin to work. Double-check it and try again.");
                    return false;
                }
            }
            else
            {
                $this->error("<strong>Error:</strong> That file does not exist in the export directory.");
                return false;
            }
        }
        else
        {
            if($this->export_type=='csv')
            {
                $export_file = str_replace('-','_',sanitize_title($this->items)).'_'.date('m-d-Y_h-i-sa').'.csv';
                $fp = fopen($this->export_dir.'/'.$export_file,'a+');
                $head = '';
                foreach($this->export_columns as $key=>$attributes)
                {
                    if(false===$attributes['display']&&false===$attributes['export'])
                        continue;
                    $head .= '"'.$attributes['label'].'",';
                }
                $head = substr($head,0,-1);
                fwrite($fp,"$head\r\n");
                foreach($this->full_data as $item)
                {
                    $line = '';
                    foreach($this->export_columns as $key=>$attributes)
                    {
                        if(false===$attributes['display']&&false===$attributes['export'])
                            continue;
                        $line .= '"'.$item[$key].'",';
                    }
                    $line = substr($line,0,-1);
                    fwrite($fp,"$line\r\n");
                }
                fclose($fp);
                $this->message('<strong>Success:</strong> Your export is ready, the download should begin in a few moments. If it doesn\'t, <a href="'.$this->export_url.urlencode($export_file).'" target="_blank">click here to access your CSV export file</a>.<br /><br />When you are done with your export, <a href="'.$this->var_update(array('remove_export'=>urlencode($export_file),'action'=>'export')).'">click here to remove it</a>, otherwise the export will be deleted within 24 hours of generation.');
                echo '<script type="text/javascript">window.open("'.$this->export_url.urlencode($export_file).'");</script>';
            }
            elseif($this->export_type=='tab')
            {
                $export_file = str_replace('-','_',sanitize_title($this->items)).'_'.date('m-d-Y_h-i-sa').'.tab';
                $fp = fopen($this->export_dir.'/'.$export_file,'a+');
                $head = '';
                foreach($this->export_columns as $key=>$attributes)
                {
                    if(false===$attributes['display']&&false===$attributes['export'])
                        continue;
                    $head .= '"'.$attributes['label'].'"'."\t";
                }
                $head = substr($head,0,-1);
                fwrite($fp,"$head\r\n");
                foreach($this->full_data as $item)
                {
                    $line = '';
                    foreach($this->export_columns as $key=>$attributes)
                    {
                        if(false===$attributes['display']&&false===$attributes['export'])
                            continue;
                        $line .= '"'.$item[$key].'"'."\t";
                    }
                    $line = substr($line,0,-1);
                    fwrite($fp,"$line\r\n");
                }
                fclose($fp);
                $this->message('<strong>Success:</strong> Your export is ready, the download should begin in a few moments. If it doesn\'t, <a href="'.$this->export_url.urlencode($export_file).'" target="_blank">click here to access your TAB export file</a>.<br /><br />When you are done with your export, <a href="'.$this->var_update(array('remove_export'=>urlencode($export_file),'action'=>'export')).'">click here to remove it</a>, otherwise the export will be deleted within 24 hours of generation.');
                echo '<script type="text/javascript">window.open("'.$this->export_url.urlencode($export_file).'");</script>';
            }
            elseif($this->export_type=='xml')
            {
                $export_file = str_replace('-','_',sanitize_title($this->items)).'_'.date('m-d-Y_h-i-sa').'.xml';
                $fp = fopen($this->export_dir.'/'.$export_file,'a+');
                $head = '<'.'?'.'xml version="1.0" encoding="utf-8" '.'?'.'>'."\r\n<items count=\"".count($this->full_data)."\">\r\n";
                $head = substr($head,0,-1);
                fwrite($fp,$head);
                foreach($this->full_data as $item)
                {
                    $line = "\t<item>\r\n";
                    foreach($this->export_columns as $key=>$attributes)
                    {
                        if(false===$attributes['display']&&false===$attributes['export'])
                            continue;
                        $line .= "\t\t<{$key}><![CDATA[".$item[$key]."]]></{$key}>\r\n";
                    }
                    $line .= "\t</item>\r\n";
                    fwrite($fp,$line);
                }
                $foot = '</items>' ;
                fwrite($fp,$foot);
                fclose($fp);
                $this->message('<strong>Success:</strong> Your export is ready, the download should begin in a few moments. If it doesn\'t, <a href="'.$this->export_url.urlencode($export_file).'" target="_blank">click here to download your XML export file</a>.<br /><br />When you are done with your export, <a href="'.$this->var_update(array('remove_export'=>urlencode($export_file),'action'=>'export')).'">click here to remove it</a>, otherwise the export will be deleted within 24 hours of generation.');
                echo '<script type="text/javascript">window.open("'.$this->export_url.urlencode($export_file).'");</script>';
            }
            elseif($this->export_type=='json')
            {
                $export_file = str_replace('-','_',sanitize_title($this->items)).'_'.date('m-d-Y_h-i-sa').'.json';
                $fp = fopen($this->export_dir.'/'.$export_file,'a+');
                $data = array('items'=>array('count'=>count($this->full_data),'item'=>array()));
                foreach($this->full_data as $item)
                {
                    $row = array();
                    foreach($this->export_columns as $key=>$attributes)
                    {
                        if(false===$attributes['display']&&false===$attributes['export'])
                            continue;
                        $row[$key] = $item[$key];
                    }
                    $data['items']['item'][] = $row;
                }
                fwrite($fp,json_encode($data));
                fclose($fp);
                $this->message('<strong>Success:</strong> Your export is ready, the download should begin in a few moments. If it doesn\'t, <a href="'.$this->export_url.urlencode($export_file).'" target="_blank">click here to access your JSON export file</a>.<br /><br />When you are done with your export, <a href="'.$this->var_update(array('remove_export'=>urlencode($export_file),'action'=>'export')).'">click here to remove it</a>, otherwise the export will be deleted within 24 hours of generation.');
                echo '<script type="text/javascript">window.open("'.$this->export_url.urlencode($export_file).'");</script>';
            }
            else
            {
                $this->error("<strong>Error:</strong> Invalid export type.");
                return false;
            }
        }
        do_action('wp_admin_ui_export',$this,$export_file);
    }
    function get_row ($id=false)
    {
        if(isset($this->custom['row'])&&function_exists("{$this->custom['row']}"))
            return $this->custom['row']($this);
        if(false===$this->table&&false===$this->sql)
            return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        if(false===$this->id&&false===$id)
            return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "id" definition.');
        if(false===$id)
            $id = $this->id;
        global $wpdb;
        $sql = "SELECT * FROM $this->table WHERE `id`=".$this->sanitize($id);
        $row = @current($wpdb->get_results($sql,ARRAY_A));
        $row = $this->do_hook('get_row',$row);
        if(!empty($row))
            $this->row = $row;
    }
    function get_data ($full=false)
    {
        if(isset($this->custom['data'])&&function_exists("{$this->custom['data']}"))
            return $this->custom['data']($this);
        if(false===$this->table&&false===$this->sql)
            return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        global $wpdb;
        if(false===$this->sql)
        {
            $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM $this->table";
            if(false!==$this->search&&false!==$this->search_query&&!empty($this->search_columns))
            {
                $and = false;
                $sql .= " WHERE ";
                foreach($this->search_columns as $key=>$column)
                {
                    if(is_array($column)&&isset($column['type'])&&$column['type']=='date')
                        continue;
                    if($and)
                        $sql .= " OR ";
                    else
                        $and = true;
                    if(is_array($column))
                        $column = $key;
                    $sql .= "`$column` LIKE '%".$this->sanitize($this->search_query)."%'";
                }
            }
            $sql .= ' ORDER BY ';
            if(isset($this->columns[$this->order])||in_array($this->order,$this->columns))
                $sql .= $this->order.' '.$this->order_dir;
            else
                $sql .= 'id';
            if(false!==$this->pagination&&!$full)
            {
                $start = ($this->page-1)*$this->limit;
                $end = ($this->page-1)*$this->limit+$this->limit;
                $sql .= " LIMIT $start,$end";
            }
        }
        else
        {
            $sql = str_replace('SELECT ','SELECT SQL_CALC_FOUND_ROWS ',str_replace('SELECT SQL_CALC_FOUND_ROWS ','SELECT ',$this->sql));
            if(false!==$this->search&&false!==$this->search_query&&!empty($this->search_columns))
            {
                $and = false;
                $sql .= " WHERE ";
                foreach($this->search_columns as $key=>$column)
                {
                    if(is_array($column)&&isset($column['type'])&&$column['type']=='date')
                        continue;
                    if($and)
                        $sql .= " OR ";
                    else
                        $and = true;
                    if(is_array($column))
                        $column = $key;
                    $sql .= "`$column` LIKE '%".$this->sanitize($this->search_query)."%'";
                }
            }
            if(false!==$this->pagination&&!$full)
            {
                $start = ($this->page-1)*$this->limit;
                $end = ($this->page-1)*$this->limit+$this->limit;
                $sql .= " LIMIT $start,$end";
            }
        }
        $results = $wpdb->get_results($sql,ARRAY_A);
        $results = $this->do_hook('get_data',$results);
        if($full)
            $this->full_data = $results;
        else
            $this->data = $results;
        if($full)
        {
            if(empty($this->columns)&&!empty($this->full_data))
            {
                $data = current($this->full_data);
                foreach($data as $data_key=>$data_value)
                    $this->columns[$data_key] = array('label'=>ucwords(str_replace('-',' ',str_replace('_',' ',$data_key))));
            }
            return;
        }
        else
        {
            if(empty($this->columns)&&!empty($this->data))
            {
                $data = current($this->data);
                foreach($data as $data_key=>$data_value)
                    $this->columns[$data_key] = array('label'=>ucwords(str_replace('-',' ',str_replace('_',' ',$data_key))));
            }
        }
        $total = @current($wpdb->get_col("SELECT FOUND_ROWS()"));
        $total = $this->do_hook('get_data_total',$total);
        if(is_numeric($total))
            $this->total = $total;
    }
    function manage ()
    {
        $this->do_hook('manage');
        if(isset($this->custom['manage'])&&function_exists("{$this->custom['manage']}"))
            return $this->custom['manage']($this);
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32"<?php if(false!==$this->icon){ ?> style="background-position:0 0;background-image:url(<?php echo $this->icon; ?>);"<?php } ?>><br /></div>
    <h2><?php echo $this->heading['manage']; ?> <?php echo $this->items; ?></h2>
<?php
        if(isset($this->custom['header'])&&function_exists("{$this->custom['header']}"))
            echo $this->custom['header']($this);
        if(empty($this->data))
            $this->get_data();
        if(false!==$this->export&&$this->action=='export')
            $this->export();
        if(!empty($this->data)&&false!==$this->search)
        {
?>
    <form id="posts-filter" action="" method="get">
        <p class="search-box">
<?php $this->hidden_vars(); ?>
            <label class="screen-reader-text" for="page-search-input">Search:</label>
<?php
            if(false!==$this->search_query)
            {
?>
            <small>[<a href="<?php echo $this->var_update(array('search_query'=>''),array('order','order_dir','limit')); ?>">Reset Filters</a>]</small>
<?php
            }
?>
            <input type="text" name="search_query" id="page-search-input" value="<?php echo $this->search_query; ?>" />
            <input type="submit" value="Search" class="button" />
        </p>
    </form>
<?php
        }
        else
        {
?>
    <br class="clear" />
    <br class="clear" />
<?php
        }
?>
    <div class="tablenav">
<?php
        if(!empty($this->data)&&false!==$this->pagination)
        {
?>
        <div class="tablenav-pages">
            Show per page:<?php $this->limit(); ?> &nbsp;|&nbsp;
<?php $this->pagination(); ?>
        </div>
<?php
        }
        if($this->add||$this->export)
        {
?>
        <div class="alignleft actions">
<?php
            if($this->add)
            {
?>
            <input type="button" value="Add New <?php echo $this->item; ?>" class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'add')); ?>'" />
<?php
            }
            if($this->export)
            {
?>
            <strong>Export:</strong>
            <input type="button" value=" CSV " class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'export','export_type'=>'csv')); ?>'" />
            <input type="button" value=" TAB " class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'export','export_type'=>'tab')); ?>'" />
            <input type="button" value=" XML " class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'export','export_type'=>'xml')); ?>'" />
            <input type="button" value=" JSON " class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'export','export_type'=>'json')); ?>'" />
<?php
            }
?>
        </div>
<?php
        }
?>
        <br class="clear" />
    </div>
    <div class="clear"></div>
<?php $this->table(); ?>
<?php
        if(!empty($this->data))
        {
?>
    <div class="tablenav">
        <div class="tablenav-pages">
<?php $this->pagination(); ?>
        <br class="clear" />
        </div>
    </div>
<?php
        }
?>
</div>
<?php
    }
    function table ()
    {
        $this->do_hook('table');
        if(isset($this->custom['table'])&&function_exists("{$this->custom['table']}"))
            return $this->custom['table']($this);
        if(empty($this->data))
        {
?>
<p>No items found</p>
<?php
            return false;
        }
?>
<table class="widefat page fixed" cellspacing="0">
    <thead>
        <tr>
<?php
        $name_column = false;
        $columns = array();
        if(!empty($this->columns)) foreach($this->columns as $column=>$attributes)
        {
            if(false===$attributes['display'])
                continue;
            if(false===$name_column)
                $id = 'title';
            else
                $id = 'author';
            if(false!==$this->get_var('type',false,false,$attributes))
            {
                if($attributes['type']=='other')
                    $id = 'author';
                if($attributes['type']=='date')
                    $id = 'date';
            }
            if(false===$name_column&&$id=='title')
                $name_column = true;
            $label = ucwords(str_replace('_',' ',$column));
            if(false!==$this->get_var('label',false,false,$attributes))
                $label = $attributes['label'];
            $columns[$column] = array('label'=>$label,'id'=>$id);
            $columns[$column] = array_merge($columns[$column],$attributes);
            $dir = 'ASC';
            if($this->order==$column&&$this->order_dir=='ASC')
                $dir = 'DESC';
?>
            <th scope="col" id="<?php echo $id; ?>" class="manage-column column-<?php echo $id; ?>"><a href="<?php echo $this->var_update(array('order'=>$column,'order_dir'=>$dir),array('limit','search')); ?>"><?php echo $label; ?></a></th>
<?php
        }
?>
        </tr>
    </thead>
    <tfoot>
        <tr>
<?php
    if(!empty($columns)) foreach($columns as $column=>$attributes)
        {
?>
            <th scope="col" class="manage-column column-<?php echo $attributes['id']; ?>"><?php echo $attributes['label']; ?></th>
<?php
        }
?>
        </tr>
    </tfoot>
    <tbody>
<?php
        if(!empty($this->data)) foreach($this->data as $row)
        {
?>
        <tr id="item-<?php echo $row['id']; ?>" class="iedit">
<?php
            foreach($columns as $column=>$attributes)
            {
                if(false===$attributes['display'])
                    continue;
                if(false!==$attributes['custom_display']&&function_exists("{$attributes['custom_display']}"))
                {
                    $row[$column] = $attributes['custom_display']($row[$column],$row,$this);
                }
                if(false!==$attributes['custom_relate'])
                {
                    global $wpdb;
                    $table = $attributes['custom_relate'];
                    $on = 'id';
                    $is = $row['id'];
                    $what = array('name');
                    if(is_array($table))
                    {
                        if(isset($table['on']))
                            $on = $this->sanitize($table['on']);
                        if(isset($table['is'])&&isset($row[$table['is']]))
                            $is = $this->sanitize($row[$table['is']]);
                        if(isset($table['what']))
                        {
                            $what = array();
                            if(is_array($table['what']))
                            {
                                foreach($table['what'] as $wha)
                                {
                                    $what[] = $this->sanitize($wha);
                                }
                            }
                            else
                            {
                                $what[] = $this->sanitize($table['what']);
                            }
                        }
                        if(isset($table['table']))
                            $table = $table['table'];
                    }
                    $table = $this->sanitize($table);
                    $wha = implode(',',$what);
                    $sql = "SELECT $wha FROM $table WHERE `$on`='$is'";
                    $value = @current($wpdb->get_results($sql,ARRAY_A));
                    if(!empty($value))
                    {
                        $val = array();
                        foreach($what as $wha)
                        {
                            if(isset($value[$wha]))
                                $val[] = $value[$wha];
                        }
                        if(!empty($val))
                            $row[$column] = implode(' ',$val);
                    }
                }
                if($attributes['id']=='title')
                {
                    if($this->view)
                    {
?>
            <td class="post-title page-title column-title"><strong><a class="row-title" href="<?php echo $this->var_update(array('action'=>'view','id'=>$row['id'])); ?>" title="View &#8220;<?php echo htmlentities($row[$column]); ?>&#8221;"><?php echo $row[$column]; ?></a></strong>
<?php
                    }
                    elseif($this->edit)
                    {
?>
            <td class="post-title page-title column-title"><strong><a class="row-title" href="<?php echo $this->var_update(array('action'=>'edit','id'=>$row['id'])); ?>" title="Edit &#8220;<?php echo htmlentities($row[$column]); ?>&#8221;"><?php echo $row[$column]; ?></a></strong>
<?php
                    }
                    else
                    {
?>
            <td class="post-title page-title column-title"><strong><?php echo $row[$column]; ?></strong>
<?php
                    }
?>
            <div class="row-actions"><?php if(isset($this->custom['action_start'])&&function_exists("{$this->custom['action_start']}")){$this->custom['action_start']($this,$row);} if($this->view){ ?><span class='view'><a href="<?php echo $this->var_update(array('action'=>'view','id'=>$row['id'])); ?>" title="View this item">View</a><?php if($this->edit||$this->delete){ ?> | <?php } ?></span><?php } if(isset($this->custom['action_end_view'])&&function_exists("{$this->custom['action_end_view']}")){$this->custom['action_end_view']($this,$row);} if($this->edit){ ?><span class='edit'><a href="<?php echo $this->var_update(array('action'=>'edit','id'=>$row['id'])); ?>" title="Edit this item">Edit</a></span><?php } if($this->delete){ ?><span class='delete'><?php if($this->edit){ ?> | <?php } ?><a class='submitdelete' title='Delete this item' href='<?php echo $this->var_update(array('action'=>'delete','id'=>$row['id'])); ?>' onclick="if ( confirm('You are about to delete this item \'<?php echo htmlentities($row[$column]); ?>\'\n \'Cancel\' to stop, \'OK\' to delete.') ) { return true;}return false;">Delete</a></span><?php } if(isset($this->custom['action_end'])&&function_exists("{$this->custom['action_end']}")){$this->custom['action_end']($this,$row);} ?></div></td>
<?php
                }
                elseif($attributes['id']=='date')
                {
?>
            <td class="date column-date"><abbr title="<?php echo date('Y/m/d g:i:s A',strtotime($row[$column])); ?>"><?php echo date('Y/m/d g:i:s A',strtotime($row[$column])); ?></abbr></td>
<?php
                }
                else
                {
?>
            <td class="author column-author"><?php echo $row[$column]; ?></td>
<?php
                }
            }
?>
        </tr>
<?php
        }
?>
    </tbody>
</table>
<script type="text/javascript">
jQuery('table.widefat tbody tr:even').addClass('alternate');
</script>
<?php
    }
    function pagination ()
    {
        $this->do_hook('pagination');
        if(isset($this->custom['pagination'])&&function_exists("{$this->custom['pagination']}"))
            return $this->custom['pagination']($this);
        $page = $this->page;
        $rows_per_page = $this->limit;
        $total_rows = $this->total;
        $total_pages = ceil($total_rows / $rows_per_page);
        $request_uri = $this->var_update(array('pg'=>''),array('limit','order','order_dir','search')).'&';
        $begin = ($rows_per_page*$page)-($rows_per_page-1);
        $end = ($total_pages==$page?$total_rows:($rows_per_page*$page));
?>
			<span class="displaying-num">Displaying <?php if($total_rows<1){ echo 0; } else { echo $begin; ?>&#8211;<?php echo $end; } ?> of <?php echo $total_rows; ?></span>
<?php
        if (1 < $page)
        {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo $page-1; ?>" class="prev page-numbers">&laquo;</a>
            <a href="<?php echo $request_uri; ?>pg=1" class="page-numbers">1</a>
<?php
        }
        if (1 < ($page - 100))
        {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page - 100); ?>" class="page-numbers"><?php echo ($page - 100); ?></a>
<?php
        }
        if (1 < ($page - 10))
        {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page - 10); ?>" class="page-numbers"><?php echo ($page - 10); ?></a>
<?php
        }
        for ($i = 2; $i > 0; $i--)
        {
            if (1 < ($page - $i))
            {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page - $i); ?>" class="page-numbers"><?php echo ($page - $i); ?></a>
<?php
           }
    }
?>
            <span class="page-numbers current"><?php echo $page; ?></span>
<?php
        for ($i = 1; $i < 3; $i++)
        {
            if ($total_pages > ($page + $i))
            {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page + $i); ?>" class="page-numbers"><?php echo ($page + $i); ?></a>
<?php
            }
        }
        if ($total_pages > ($page + 10))
        {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page + 10); ?>" class="page-numbers"><?php echo ($page + 10); ?></a>
<?php
        }
        if ($total_pages > ($page + 100))
        {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page + 100); ?>" class="page-numbers"><?php echo ($page + 100); ?></a>
<?php
        }
        if ($page < $total_pages)
        {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo $total_pages; ?>" class="page-numbers"><?php echo $total_pages; ?></a>
            <a href="<?php echo $request_uri; ?>pg=<?php echo $page+1; ?>" class="next page-numbers">&raquo;</a>
<?php
        }
    }
    function limit ($options=false)
    {
        $this->do_hook('limit',$options);
        if(isset($this->custom['limit'])&&function_exists("{$this->custom['limit']}"))
            return $this->custom['limit']($this);
        if(false===$options||!is_array($options)||empty($options))
            $options = array(10,25,50,100,200);
        if(!in_array($this->limit,$options))
            $this->limit = $options[1];
        foreach($options as $option)
        {
            if($this->limit==$option)
                echo ' <span class="page-numbers current">'.$option.'</span>';
            else
                echo ' <a href="'.$this->var_update(array('limit'=>$option),array('order','order_dir','search')).'">'.$option.'</a>';
        }
    }
}