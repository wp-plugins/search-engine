<?php
global $wpdb;
if(!is_object($wpdb))
{
    ob_start();
    if(file_exists(realpath('../../../../wp-load.php')))
        require_once(realpath('../../../../wp-load.php'));
    else
        require_once(realpath('../../../wp-load.php'));
    ob_end_clean();
}
// FOR EXPORTS ONLY
if(isset($_GET['download'])&&!isset($_GET['page'])&&is_user_logged_in())
{
    do_action('wp_admin_ui_export_download');
    $file = WP_CONTENT_DIR.'/exports/'.str_replace('/','',$_GET['export']);
    if(!isset($_GET['export'])||empty($_GET['export'])||!file_exists($file))
        die('File not found.');
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
 * NOTE: If you are including this class code in a plugin,
 * consider renaming the class to avoid conflicts with other plugins.
 * This is not required, but some developers may include the class
 * the wrong way which could cause an issue with your including the
 * class file.
 *
 * @package Admin UI for Plugins
 *
 * @version 1.6.0
 * @author Scott Kingsley Clark
 * @link http://www.scottkclark.com/
 *
 * @param mixed $options
 */
class WP_Admin_UI
{
    // base
    var $table = false;
    var $identifier = 'id';
    var $sql = false;
    var $id = false;
    var $action = 'manage';
    var $do = false;
    var $search = true;
    var $filters = array();
    var $search_query = false;
    var $pagination = true;
    var $page = 1;
    var $limit = 25;
    var $order = false;
    var $order_dir = 'DESC';
    var $reorder_order = false;
    var $reorder_order_dir = 'ASC';

    // ui
    var $item = 'Item';
    var $items = 'Items';
    var $heading = array('manage'=>'Manage','add'=>'Add New','edit'=>'Edit','duplicate'=>'Duplicate','view'=>'View','reorder'=>'Reorder');
    var $icon = false;
    var $css = false;

    // actions
    var $add = true;
    var $view = false;
    var $edit = true;
    var $duplicate = false;
    var $delete = true;
    var $save = true;
    var $readonly = false;
    var $export = false;
    var $reorder = false;

    // array of custom functions to run for actions
    var $custom = array();

    // data related
    var $total = 0;
    var $columns = array();
    var $data = array();
    var $full_data = array();
    var $row = array();
    var $search_columns = array();
    var $form_columns = array();
    var $view_columns = array();
    var $export_columns = array();
    var $reorder_columns = array();
    var $insert_id = 0;

    // export related
    var $export_dir = false;
    var $export_url = false;
    var $export_type = false;
    var $export_delimiter = false;

    function __construct ($options=false)
    {
        do_action('wp_admin_ui_pre_init',$options);
        $options = $this->do_hook('options',$options);
        $this->base_url = WP_CONTENT_URL.str_replace(WP_CONTENT_DIR,'',__FILE__);
        $this->export_dir = WP_CONTENT_DIR.'/exports';
        $this->export_url = $this->base_url.'?download=1&export=';
        $this->assets_url = str_replace('/Admin.class.php','',$this->base_url).'/assets';
        if(false!==$this->get_var('id'))
            $this->id = $_GET['id'];
        if(false!==$this->get_var('action',false,array('add','edit','duplicate','view','delete','manage','reorder','export')))
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
        if(false!==$this->get_var('action',false,'export')&&false!==$this->get_var('export_type',false,array('csv','tsv','pipe','custom','xml','json')))
            $this->export_type = $_GET['export_type'];
        if(false!==$this->get_var('action',false,'export')&&'custom'==$this->export_type&&false!==$this->get_var('export_delimiter'))
            $this->export_delimiter = $_GET['export_delimiter'];
        if(false!==$options&&!empty($options))
        {
            if(!is_array($options))
                parse_str($options,$options);
            foreach($options as $option=>$value)
                $this->$option = $value;
        }
        if(false!==$this->readonly)
            $this->add = $this->edit = $this->delete = $this->save = $this->reorder = false;
        if(false===$this->order)
            $this->order = $this->identifier;
        if(false!==$this->reorder&&false===$this->reorder_order)
            $this->reorder_order = $this->reorder;
        $this->columns = $this->setup_columns();
        if(!empty($this->filters)&&!is_array($this->filters))
            $this->filters = implode(',',$this->filters);
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
    function hidden_vars ($exclude=false)
    {
        $exclude = $this->do_hook('hidden_vars',$exclude);
        if(false===$exclude)
            $exclude = array();
        if(!is_array($exclude))
            $exclude = explode(',',$exclude);
        foreach($_GET as $k=>$v)
        {
            if(in_array($k,$exclude))
                continue;
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
        $excluded = array('do','id','pg','search_query','order','order_dir','limit','action','export','export_type','export_delimiter','remove_export','updated','duplicate');
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
        return $this->do_hook('var_update',$url.'?'.http_build_query($get),$array,$allowed,$url);
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
    function setup_columns ($columns=null,$which='columns')
    {
        $init = false;
        if(null===$columns)
        {
            $columns = $this->$which;
            if($which=='columns')
                $init = true;
        }
        if(!empty($columns))
        {
            // Available Attributes
            // type = field type
                // type = date (data validation as date)
                // type = time (data validation as time)
                // type = datetime (data validation as datetime)
                    // date_touch = use current timestamp when saving (even if readonly, if type is date-related)
                    // date_touch_on_create = use current timestamp when saving ONLY on create (even if readonly, if type is date-related)
                    // date_ongoing = use this additional column to search between as if the first is the "start" and the date_ongoing is the "end" for filter
                // type = text / other (single line text box)
                // type = desc (textarea)
                // type = number (data validation as int float)
                // type = decimal (data validation as decimal)
                // type = password (single line password box)
                // type = bool (single line password box)
                // type = related (select box)
                    // related = table to relate to (if type=related) OR custom array of (key=>label or comma separated values) items
                    // related_field = field name on table to show (if type=related) - default "name"
                    // related_multiple = true (ability to select multiple values if type=related)
                    // related_sql = custom where / order by SQL (if type=related)
            // readonly = true (shows as text)
            // display = false (doesn't show on form, but can be saved)
            // search = this field is searchable
            // filter = this field will be independantly searchable (by default, searchable fields are searched by the primary search box)
            // comments = comments to show for field
            // comments_top = true (shows comments above field instead of below)
            $new_columns = array();
            $filterable = false;
            if(empty($this->filters)&&(empty($this->search_columns)||$which=='search_columns')&&false!==$this->search)
            {
                $filterable = true;
                $this->filters = array();
            }
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
                if('related'!=$attributes['type']||!isset($attributes['related']))
                    $attributes['related'] = false;
                if('related'!=$attributes['type']||!isset($attributes['related_field']))
                    $attributes['related_field'] = 'name';
                if('related'!=$attributes['type']||!isset($attributes['related_multiple']))
                    $attributes['related_multiple'] = false;
                if('related'!=$attributes['type']||!isset($attributes['related_sql']))
                    $attributes['related_sql'] = false;
                if('related'==$attributes['type']&&(is_array($attributes['related'])||strpos($attributes['related'],',')))
                {
                    if(!is_array($attributes['related']))
                    {
                        $attributes['related'] = @explode(',',$attributes['related']);
                        $related_items = array();
                        foreach($attributes['related'] as $key=>$label)
                        {
                            if(is_numeric($key))
                            {
                                $key = $label;
                                $label = ucwords(str_replace('_',' ',$label));
                            }
                            $related_items[$key] = $label;
                        }
                        $attributes['related'] = $related_items;
                    }
                    if(empty($attributes['related']))
                        $attributes['related'] = false;
                }
                if(!isset($attributes['readonly']))
                    $attributes['readonly'] = false;
                if(!isset($attributes['date_touch'])||!in_array($attributes['type'],array('date','time','datetime')))
                    $attributes['date_touch'] = false;
                if(!isset($attributes['date_touch_on_create'])||!in_array($attributes['type'],array('date','time','datetime')))
                    $attributes['date_touch_on_create'] = false;
                if(!isset($attributes['display']))
                    $attributes['display'] = true;
                if(!isset($attributes['search'])||false===$this->search)
                    $attributes['search'] =(false!==$this->search?true:false);
                if(!isset($attributes['filter'])||false===$this->search)
                    $attributes['filter'] = false;
                if(false!==$attributes['filter']&&false!==$filterable)
                    $this->filters[] = $column;
                if(false===$attributes['filter']||!isset($attributes['filter_label'])||!in_array($column,$this->filters))
                    $attributes['filter_label'] = $attributes['label'];
                if(false===$attributes['filter']||!isset($attributes['date_ongoing'])||!in_array($attributes['type'],array('date','time','datetime'))||!in_array($column,$this->filters))
                    $attributes['date_ongoing'] = false;
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
            if(!empty($this->form_columns)&&($this->edit||$this->add||$this->duplicate))
                $this->form_columns = $this->setup_columns($this->form_columns,'form_columns');
            else
                $this->form_columns = $columns;
            if(!empty($this->search_columns)&&$this->search)
                $this->search_columns = $this->setup_columns($this->search_columns,'search_columns');
            else
                $this->search_columns = $columns;
            if(!empty($this->export_columns)&&$this->export)
                $this->export_columns = $this->setup_columns($this->export_columns,'export_columns');
            else
                $this->export_columns = $columns;
            if(!empty($this->reorder_columns)&&$this->reorder)
                $this->reorder_columns = $this->setup_columns($this->reorder_columns,'reorder_columns');
            else
                $this->reorder_columns = $columns;
        }
        return $this->do_hook('setup_columns',$columns,$which,$init);
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
        elseif(($this->action=='edit'&&$this->edit)||($this->action=='duplicate'&&$this->duplicate))
        {
            if($this->do=='save'&&$this->save)
            {
                $this->save();
            }
            $this->edit(($this->action=='duplicate'&&$this->duplicate?1:0));
        }
        elseif($this->action=='delete'&&$this->delete)
        {
            $this->delete();
            $this->manage();
        }
        elseif($this->action=='reorder'&&$this->reorder)
        {
            if(false===$this->table)
                return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
            if(false===$this->identifier)
                return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "identifier" definition.');
            if($this->do=='save')
            {
                $this->reorder();
            }
            $this->manage(1);
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
    <h2><?php echo $this->heading['add']; ?> <?php echo $this->item; ?> <small>(<a href="<?php echo $this->var_update(array('action'=>'manage','id'=>'')); ?>">&laquo; Back to Manage</a>)</small></h2>
<?php $this->form(1); ?>
</div>
<?php
    }
    function edit ($duplicate=0)
    {
        if(!$this->duplicate)
            $duplicate = 0;
        $this->do_hook('edit',$duplicate);
        if(isset($this->custom['edit'])&&function_exists("{$this->custom['edit']}"))
            $this->custom['edit']($this,$duplicate);
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32"<?php if(false!==$this->icon){ ?> style="background-position:0 0;background-image:url(<?php echo $this->icon; ?>);"<?php } ?>><br /></div>
    <h2><?php echo ($duplicate?$this->heading['duplicate']:$this->heading['edit']); ?> <?php echo $this->item; ?> <small>(<a href="<?php echo $this->var_update(array('action'=>'manage','id'=>'')); ?>">&laquo; Back to Manage</a>)</small></h2>
<?php $this->form(0,$duplicate); ?>
</div>
<?php
    }
    function form ($create=0,$duplicate=0)
    {
        $this->do_hook('form',$create,$duplicate);
        if(isset($this->custom['form'])&&function_exists("{$this->custom['form']}"))
            return $this->custom['form']($this,$create);
        if(false===$this->table&&false===$this->sql)
            return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        global $wpdb;
        if(empty($this->form_columns))
            $this->form_columns = $this->columns;
        $submit = 'Add '.$this->item;
        $id = '';
        $vars = array('action'=>'manage','do'=>'create','id'=>'');
        if($create==0)
        {
            if(empty($this->row))
                $this->get_row();
            if(empty($this->row))
                return $this->error("<strong>Error:</strong> $this->item not found.");
            if($duplicate==0)
            {
                $submit = 'Save Changes';
                $id = $this->row[$this->identifier];
                $vars = array('action'=>'edit','do'=>'save','id'=>$id);
            }
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
                    if(!is_array($attributes['related']))
                    {
                        $related = $wpdb->get_results('SELECT id,`'.$attributes['related_field'].'` FROM '.$attributes['related'].(!empty($attributes['related_sql'])?' '.$attributes['related_sql']:''));
?>
            <select name="<?php echo $column; ?><?php echo (false!==$attributes['related_multiple']?'[]':''); ?>" id="admin_ui_<?php echo $column; ?>"<?php echo (false!==$attributes['related_multiple']?' size="10" style="height:auto;" MULTIPLE':''); ?>>
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
                        $related = $attributes['related'];
?>
            <select name="<?php echo $column; ?><?php echo (false!==$attributes['related_multiple']?'[]':''); ?>" id="admin_ui_<?php echo $column; ?>"<?php echo (false!==$attributes['related_multiple']?' size="10" style="height:auto;" MULTIPLE':''); ?>>
<?php
                        $selected_options = explode(',',$this->row[$column]);
                        foreach($related as $option_id=>$option)
                        {
?>
                <option value="<?php echo $option_id; ?>"<?php echo (in_array($option_id,$selected_options)?' SELECTED':''); ?>><?php echo $option; ?></option>
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
    <h2><?php echo $this->heading['view']; ?> <?php echo $this->item; ?> <small>(<a href="<?php echo $this->var_update(array('action'=>'manage','id'=>'')); ?>">&laquo; Back to Manage</a>)</small></h2>
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
            elseif($attributes['type']=='related'&&false!==$attributes['related'])
            {
                $old_value = $this->row[$column];
                $this->row[$column] = '';
                if(!empty($old_value))
                {
                    $this->row[$column] = array();
                    if(!is_array($attributes['related']))
                    {
                        $related = $wpdb->get_results('SELECT id,`'.$attributes['related_field'].'` FROM '.$attributes['related'].' WHERE id IN ('.$old_value.')'.(!empty($attributes['related_sql'])?' '.$attributes['related_sql']:''));
                        foreach($related as $option)
                        {
                            $this->row[$column][] = $option->$attributes['related_field'];
                        }
                    }
                    else
                    {
                        $related = $attributes['related'];
                        $selected_options = explode(',',$old_value);
                        foreach($related as $option_id=>$option)
                        {
                            if(in_array($option_id,$selected_options))
                            {
                                $this->row[$column][] = $option;
                            }
                        }
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
        $this->do_hook('pre_delete',$id);
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
            return $this->custom['save']($this,$create);
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
                if(!in_array($attributes['type'],array('date','time','datetime')))
                    continue;
                if(false===$attributes['date_touch']&&(false===$attributes['date_touch_on_create']||$create!=1||$this->id>0))
                    continue;
            }
            if(in_array($attributes['type'],array('date','time','datetime')))
            {
                $format = "Y-m-d H:i:s";
                if($attributes['type']=='date')
                    $format = "Y-m-d";
                if($attributes['type']=='time')
                    $format = "H:i:s";
                if(false!==$attributes['date_touch']||(false!==$attributes['date_touch_on_create']&&$create==1&&$this->id<1))
                    $value = date($format);
                else
                {
                    $value = date($format,strtotime(($attributes['type']=='time'?date('Y-m-d '):'').$_POST[$column]));
                }
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
            $this->error('<strong>Error:</strong> '.$this->item.' has not been '.$action.'.');
        $this->do_hook('post_save',$this->insert_id,$data,$create);
    }
    function reorder ()
    {
        $this->do_hook('pre_reorder');
        if(isset($this->custom['reorder'])&&function_exists("{$this->custom['reorder']}"))
            return $this->custom['reorder']($this);
        global $wpdb;
        if(false===$this->table)
            return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        if(false===$this->identifier)
            return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "identifier" definition.');
        if(isset($_POST['order'])&&!empty($_POST['order']))
        {
            foreach($_POST['order'] as $order => $id)
                $updated = $wpdb->update($this->table,array($this->reorder=>$order),array($this->identifier=>$id),array('%s','%d'),array('%d'));
            $this->message('<strong>Success!</strong> Order updated successfully.');
        }
        else
            $this->error('<strong>Error:</strong> Order has not been updated.');
        $this->do_hook('post_reorder',$this->insert_id,$data);
    }
    function export ()
    {
        $this->do_hook('pre_export');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $url = explode('/',$_SERVER['REQUEST_URI']);
        $url = array_reverse($url);
        $url = $url[0];
        if(false===($credentials=request_filesystem_credentials($url,'',false,ABSPATH)))
        {
            $this->error("<strong>Error:</strong> Your hosting configuration does not allow access to add files to your site.");
            return false;
        }
        if(!WP_Filesystem($credentials,ABSPATH))
        {
            request_filesystem_credentials($url,'',true,ABSPATH); //Failed to connect, Error and request again
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
            $this->do_hook('pre_remove_export',$this->export_dir.'/'.str_replace('/','',$_GET['remove_export']));
            if($wp_filesystem->exists($this->export_dir.'/'.str_replace('/','',$_GET['remove_export'])))
            {
                $remove = @unlink($this->export_dir.'/'.str_replace('/','',$_GET['remove_export']));
                if($remove)
                {
                    $this->do_hook('post_remove_export',$_GET['remove_export'],true);
                    $this->message('<strong>Success:</strong> Export removed successfully.');
                    return;
                }
                else
                {
                    $this->do_hook('post_remove_export',$_GET['remove_export'],false);
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
                $head = array();
                $first = true;
                foreach($this->export_columns as $key=>$attributes)
                {
                    if(false===$attributes['display']&&false===$attributes['export'])
                        continue;
                    if($first)
                    {
                        $attributes['label'] .= ' ';
                        $first = false;
                    }
                    $head[] = $attributes['label'];
                }
                fputcsv($fp,$head,",");
                foreach($this->full_data as $item)
                {
                    $line = array();
                    foreach($this->export_columns as $key=>$attributes)
                    {
                        if(false===$attributes['display']&&false===$attributes['export'])
                            continue;
                        $line[] = $item[$key];
                    }
                    fputcsv($fp,$line,",");
                }
                fclose($fp);
                $this->message('<strong>Success:</strong> Your export is ready, the download should begin in a few moments. If it doesn\'t, <a href="'.$this->export_url.urlencode($export_file).'" target="_blank">click here to access your CSV export file</a>.<br /><br />When you are done with your export, <a href="'.$this->var_update(array('remove_export'=>urlencode($export_file),'action'=>'export')).'">click here to remove it</a>, otherwise the export will be deleted within 24 hours of generation.');
                echo '<script type="text/javascript">window.open("'.$this->export_url.urlencode($export_file).'");</script>';
            }
            elseif($this->export_type=='tsv')
            {
                $export_file = str_replace('-','_',sanitize_title($this->items)).'_'.date('m-d-Y_h-i-sa').'.tsv';
                $fp = fopen($this->export_dir.'/'.$export_file,'a+');
                $head = array();
                $first = true;
                foreach($this->export_columns as $key=>$attributes)
                {
                    if(false===$attributes['display']&&false===$attributes['export'])
                        continue;
                    if($first)
                    {
                        $attributes['label'] .= ' ';
                        $first = false;
                    }
                    $head[] = $attributes['label'];
                }
                fputcsv($fp,$head,"\t");
                foreach($this->full_data as $item)
                {
                    $line = array();
                    foreach($this->export_columns as $key=>$attributes)
                    {
                        if(false===$attributes['display']&&false===$attributes['export'])
                            continue;
                        $line[] = $item[$key];
                    }
                    fputcsv($fp,$line,"\t");
                }
                fclose($fp);
                $this->message('<strong>Success:</strong> Your export is ready, the download should begin in a few moments. If it doesn\'t, <a href="'.$this->export_url.urlencode($export_file).'" target="_blank">click here to access your TSV export file</a>.<br /><br />When you are done with your export, <a href="'.$this->var_update(array('remove_export'=>urlencode($export_file),'action'=>'export')).'">click here to remove it</a>, otherwise the export will be deleted within 24 hours of generation.');
                echo '<script type="text/javascript">window.open("'.$this->export_url.urlencode($export_file).'");</script>';
            }
            elseif($this->export_type=='pipe')
            {
                $export_file = str_replace('-','_',sanitize_title($this->items)).'_'.date('m-d-Y_h-i-sa').'.txt';
                $fp = fopen($this->export_dir.'/'.$export_file,'a+');
                $head = array();
                $first = true;
                foreach($this->export_columns as $key=>$attributes)
                {
                    if(false===$attributes['display']&&false===$attributes['export'])
                        continue;
                    if($first)
                    {
                        $attributes['label'] .= ' ';
                        $first = false;
                    }
                    $head[] = $attributes['label'];
                }
                fputcsv($fp,$head,"|");
                foreach($this->full_data as $item)
                {
                    $line = array();
                    foreach($this->export_columns as $key=>$attributes)
                    {
                        if(false===$attributes['display']&&false===$attributes['export'])
                            continue;
                        $line[] = $item[$key];
                    }
                    fputcsv($fp,$line,"|");
                }
                fclose($fp);
                $this->message('<strong>Success:</strong> Your export is ready, the download should begin in a few moments. If it doesn\'t, <a href="'.$this->export_url.urlencode($export_file).'" target="_blank">click here to access your TXT export file</a>.<br /><br />When you are done with your export, <a href="'.$this->var_update(array('remove_export'=>urlencode($export_file),'action'=>'export')).'">click here to remove it</a>, otherwise the export will be deleted within 24 hours of generation.');
                echo '<script type="text/javascript">window.open("'.$this->export_url.urlencode($export_file).'");</script>';
            }
            elseif($this->export_type=='custom')
            {
                $export_file = str_replace('-','_',sanitize_title($this->items)).'_'.date('m-d-Y_h-i-sa').'.txt';
                $fp = fopen($this->export_dir.'/'.$export_file,'a+');
                $head = array();
                $first = true;
                foreach($this->export_columns as $key=>$attributes)
                {
                    if(false===$attributes['display']&&false===$attributes['export'])
                        continue;
                    if($first)
                    {
                        $attributes['label'] .= ' ';
                        $first = false;
                    }
                    $head[] = $attributes['label'];
                }
                fputcsv($fp,$head,"$this->export_delimiter");
                foreach($this->full_data as $item)
                {
                    $line = array();
                    foreach($this->export_columns as $key=>$attributes)
                    {
                        if(false===$attributes['display']&&false===$attributes['export'])
                            continue;
                        $line[] = $item[$key];
                    }
                    fputcsv($fp,$line,"$this->export_delimiter");
                }
                fclose($fp);
                $this->message('<strong>Success:</strong> Your export is ready, the download should begin in a few moments. If it doesn\'t, <a href="'.$this->export_url.urlencode($export_file).'" target="_blank">click here to access your TXT export file</a>.<br /><br />When you are done with your export, <a href="'.$this->var_update(array('remove_export'=>urlencode($export_file),'action'=>'export')).'">click here to remove it</a>, otherwise the export will be deleted within 24 hours of generation.');
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
        $this->do_hook('post_export',$export_file);
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
        $row = $this->do_hook('get_row',$row,$id);
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
            if(false!==$this->search&&!empty($this->search_columns))
            {
                $whered = false;
                if(false!==$this->search_query&&0<strlen($this->search_query))
                {
                    $sql .= " WHERE ";
                    $whered = true;
                    $other_sql = array();
                    foreach($this->search_columns as $key=>$column)
                    {
                        if(false===$column['search'])
                            continue;
                        if(in_array($attributes['type'],array('date','time','datetime')))
                            continue;
                        if(is_array($column))
                            $column = $key;
                        $other_sql[] = "`$column` LIKE '%".$this->sanitize($this->search_query)."%'";
                    }
                    if(!empty($other_sql))
                    {
                        $sql .= '('.implode(' OR ',$other_sql).')';
                    }
                }
                $other_sql = array();
                foreach($this->filters as $filter)
                {
                    if(!isset($this->search_columns[$filter]))
                        continue;
                    if(in_array($this->search_columns[$filter]['type'],array('date','datetime')))
                    {
                        $start = date('Y-m-d').' 00:00:00';
                        $end = date('Y-m-d').' 23:59:59';
                        if(strlen($this->get_var('filter_'.$filter.'_start',false))<1&&strlen($this->get_var('filter_'.$filter.'_end',false))<1)
                            continue;
                        if(0<strlen($this->get_var('filter_'.$filter.'_start',false)))
                            $start = date('Y-m-d',strtotime($this->get_var('filter_'.$filter.'_start',false))).' 00:00:00';
                        if(0<strlen($this->get_var('filter_'.$filter.'_end',false)))
                            $end = date('Y-m-d',strtotime($this->get_var('filter_'.$filter.'_end',false))).' 23:59:59';
                        if(false!==$this->search_columns[$filter]['date_ongoing'])
                        {
                            $other_sql[] = "((`$filter` <= '$start' OR `$filter` <= '$end') AND (`".$this->search_columns[$filter]['date_ongoing']."` >= '$end' OR `".$this->search_columns[$filter]['date_ongoing']."` >= '$start'))";
                        }
                        else
                            $other_sql[] = "(`$filter` BETWEEN '$start' AND '$end')";
                    }
                    elseif(false!==$this->get_var('filter_'.$filter,false))
                    {
                        $other_sql[] = "`$filter` LIKE '%".$this->sanitize($this->get_var('filter_'.$filter,false))."%'";
                    }
                }
                if(!empty($other_sql))
                {
                    if(false===$whered)
                        $sql .= " WHERE ";
                    else
                        $sql .= " AND ";
                    $sql .= implode(' AND ',$other_sql);
                }
            }
            $sql .= ' ORDER BY ';
            if(false!==$this->order&&(false===$this->reorder||$this->action!='reorder'))
                $sql .= $this->order.' '.$this->order_dir;
            elseif(false!==$this->reorder&&$this->action=='reorder')
                $sql .= $this->reorder_order.' '.$this->reorder_order_dir;
            else
                $sql .= $this->identifier;
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
                    if(is_array($column)&&isset($column['type'])&&in_array($attributes['type'],array('date','time','datetime')))
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
        $results = $this->do_hook('get_data',$results,$full);
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
                $this->export_columns = $this->columns;
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
                $this->export_columns = $this->columns;
            }
        }
        $total = @current($wpdb->get_col("SELECT FOUND_ROWS()"));
        $total = $this->do_hook('get_data_total',$total,$full);
        if(is_numeric($total))
            $this->total = $total;
    }
    function manage ($reorder=0)
    {
        $this->do_hook('manage',$reorder);
        if(isset($this->custom['manage'])&&function_exists("{$this->custom['manage']}"))
            return $this->custom['manage']($this,$reorder);
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32"<?php if(false!==$this->icon){ ?> style="background-position:0 0;background-image:url(<?php echo $this->icon; ?>);"<?php } ?>><br /></div>
    <h2><?php echo ($reorder==0||false===$this->reorder?$this->heading['manage']:$this->heading['reorder']); ?> <?php echo $this->items; if($reorder==1&&false!==$this->reorder){ ?> <small>(<a href="<?php echo $this->var_update(array('action'=>'manage','id'=>'')); ?>">&laquo; Back to Manage</a>)</small><?php } ?></h2>
<?php
        if(isset($this->custom['header'])&&function_exists("{$this->custom['header']}"))
            echo $this->custom['header']($this);
        if(empty($this->data))
            $this->get_data();
        if(false!==$this->export&&$this->action=='export')
            $this->export();
        if((!empty($this->data)||false!==$this->search_query)&&false!==$this->search)
        {
?>
    <form id="posts-filter" action="" method="get">
        <p class="search-box">
<?php
            $hidden_filters = array();
            foreach($this->filters as $filter)
            {
                $hidden_filters['filter_'.$filter.'_start'] = '';
                $hidden_filters['filter_'.$filter.'_end'] = '';
                $hidden_filters['filter_'.$filter] = '';
            }
            $search_filters = array_merge($hidden_filters,array('search_query'));
            $this->hidden_vars($search_filters);
            foreach($this->filters as $filter)
            {
                if(!isset($this->search_columns[$filter]))
                    continue;
                $date_exists = false;
                if(in_array($this->search_columns[$filter]['type'],array('date','datetime')))
                {
                    if(false===$date_exists)
                    {
?>
<link type="text/css" rel="stylesheet" href="<?php echo $this->assets_url; ?>/jquery/ui.datepicker.css" />
<script type="text/javascript">
jQuery(document).ready(function(){
    jQuery.getScript('<?php echo $this->assets_url; ?>/jquery/ui.datepicker.js',function(){jQuery('input.admin_ui_date').datepicker();});
});
</script>
<?php
                    }
                    $date_exists = true;
                    $start = $this->get_var('filter_'.$filter.'_start',false);
                    $end = $this->get_var('filter_'.$filter.'_end',false);
?>&nbsp;&nbsp;
            <label for="admin_ui_filter_<?php echo $filter; ?>_start"><?php echo $this->search_columns[$filter]['filter_label']; ?>:</label>
            <input type="text" name="filter_<?php echo $filter; ?>_start" class="admin_ui_filter admin_ui_date" id="admin_ui_filter_<?php echo $filter; ?>_start" value="<?php echo (false!==$start&&0<strlen($start)?date('m/d/Y',strtotime($start)):''); ?>" /> <label for="admin_ui_filter_<?php echo $filter; ?>_end">to</label>
            <input type="text" name="filter_<?php echo $filter; ?>_end" class="admin_ui_filter admin_ui_date" id="admin_ui_filter_<?php echo $filter; ?>_end" value="<?php echo (false!==$end&&0<strlen($end)?date('m/d/Y',strtotime($end)):''); ?>" />
<?php
                }
                else
                {
?>
            <label for="admin_ui_filter_<?php echo $filter; ?>"><?php echo $this->search_columns[$filter]['filter_label']; ?>:</label>
            <input type="text" name="filter_<?php echo $filter; ?>" class="admin_ui_filter" id="admin_ui_filter_<?php echo $filter; ?>" value="<?php echo $this->get_var('filter_'.$filter,''); ?>" />
<?php
                }
            }
?>&nbsp;&nbsp;
            <label<?php echo(empty($this->filters)?' class="screen-reader-text"':''); ?> for="page-search-input">Search:</label>
            <input type="text" name="search_query" id="page-search-input" value="<?php echo $this->search_query; ?>" />
            <input type="submit" value="Search" class="button" />
<?php
            if(false!==$this->search_query)
            {
                $clear_filters = array();
                foreach($search_filters as $filter)
                {
                    $clear_filters['filter_'.$filter.'_start'] = '';
                    $clear_filters['filter_'.$filter.'_end'] = '';
                    $clear_filters['filter_'.$filter] = '';
                }
?>
            &nbsp;&nbsp;&nbsp;<small>[<a href="<?php echo $this->var_update($clear_filters,array('order','order_dir','limit')); ?>">Reset Filters</a>]</small>
<?php
        }
?>
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
        if($reorder==1)
        {
?>
            <input type="button" value="Update Order" class="button" onclick="jQuery('form.admin_ui_reorder_form').submit();" />
            <input type="button" value="Cancel" class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'manage')); ?>';" />
<?php
        }
        elseif($this->add||$this->export)
        {
?>
        <div class="alignleft actions">
<?php
            if($this->add)
            {
?>
            <input type="button" value="Add New <?php echo $this->item; ?>" class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'add')); ?>';" />
<?php
            }
            if($this->reorder)
            {
?>
            <input type="button" value="Reorder" class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'reorder')); ?>';" />
<?php
            }
            if($this->export)
            {
?>
            <strong>Export:</strong>
            <input type="button" value=" CSV " class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'export','export_type'=>'csv')); ?>';" />
            <input type="button" value=" TSV " class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'export','export_type'=>'tsv')); ?>';" />
            <input type="button" value=" XML " class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'export','export_type'=>'xml')); ?>';" />
            <input type="button" value=" JSON " class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'export','export_type'=>'json')); ?>';" />
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
<?php $this->table($reorder); ?>
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
    function table ($reorder=0)
    {
        $this->do_hook('table',$reorder);
        if(isset($this->custom['table'])&&function_exists("{$this->custom['table']}"))
            return $this->custom['table']($this,$reorder);
        if(empty($this->data))
        {
?>
<p>No items found</p>
<?php
            return false;
        }
        if($reorder==1&&$this->reorder)
        {
?>
<style type="text/css">
table.widefat.fixed tbody.sortable tr { height:50px; }
.dragme {
    background: url(<?php echo $this->assets_url; ?>/move.png) no-repeat;
    background-position:8px 5px;
    cursor:pointer;
}
.dragme strong { margin-left:30px; }
</style>
<form action="<?php echo $this->var_update(array('action'=>'reorder','do'=>'save')); ?>" method="post" class="admin_ui_reorder_form">
<?php
        }
        $column_index = 'columns';
        if($reorder==1)
            $column_index = 'reorder_columns';
        if(false===$this->$column_index||empty($this->$column_index))
            return $this->error('<strong>Error:</strong> Invalid Configuration - Missing "columns" definition.');
?>
<table class="widefat page fixed admin_ui_table" cellspacing="0"<?php echo ($reorder==1&&$this->reorder?' id="admin_ui_reorder"':''); ?>>
    <thead>
        <tr>
<?php
        $name_column = false;
        $columns = array();
        if(!empty($this->$column_index)) foreach($this->columns as $column=>$attributes)
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
            <th scope="col" id="<?php echo $id; ?>" class="manage-column column-<?php echo $id; ?>"><a href="<?php echo $this->var_update(array('order'=>$column,'order_dir'=>$dir),array('limit','search_query')); ?>"><?php echo $label; ?></a></th>
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
    <tbody<?php echo ($reorder==1&&$this->reorder?' class="sortable"':''); ?>>
<?php
        if(!empty($this->data)) foreach($this->data as $row)
        {
?>
        <tr id="item-<?php echo $row[$this->identifier]; ?>" class="iedit">
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
                    $on = $this->identifier;
                    $is = $row[$this->identifier];
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
                    if($this->view&&($reorder==0||false===$this->reorder))
                    {
?>
            <td class="post-title page-title column-title"><strong><a class="row-title" href="<?php echo $this->var_update(array('action'=>'view','id'=>$row[$this->identifier])); ?>" title="View &#8220;<?php echo htmlentities($row[$column]); ?>&#8221;"><?php echo $row[$column]; ?></a></strong>
<?php
                    }
                    elseif($this->edit&&($reorder==0||false===$this->reorder))
                    {
?>
            <td class="post-title page-title column-title"><strong><a class="row-title" href="<?php echo $this->var_update(array('action'=>'edit','id'=>$row[$this->identifier])); ?>" title="Edit &#8220;<?php echo htmlentities($row[$column]); ?>&#8221;"><?php echo $row[$column]; ?></a></strong>
<?php
                    }
                    else
                    {
?>
            <td class="post-title page-title column-title<?php echo ($reorder==1&&$this->reorder?' dragme':''); ?>"><strong><?php echo $row[$column]; ?></strong>
<?php
                    }
                    if($reorder==0||false===$this->reorder)
                    {
                        $actions = array();
                        if($this->view)
                        {
                            $actions['view'] = '<span class="view"><a href="'.$this->var_update(array('action'=>'view','id'=>$row[$this->identifier])).'" title="View this item">View</a></span>';
                        }
                        if($this->edit)
                        {
                            $actions['edit'] = '<span class="edit"><a href="'.$this->var_update(array('action'=>'edit','id'=>$row[$this->identifier])).'" title="Edit this item">Edit</a></span>';
                        }
                        if($this->duplicate)
                        {
                            $actions['duplicate'] = '<span class="edit"><a href="'.$this->var_update(array('action'=>'duplicate','id'=>$row[$this->identifier])).'" title="Duplicate this item">Duplicate</a></span>';
                        }
                        if($this->delete)
                        {
                            $actions['delete'] = '<span class="delete"><a class="submitdelete" title="Delete this item" href="'.$this->var_update(array('action'=>'delete','id'=>$row[$this->identifier])).'" onclick="if(confirm(\'You are about to delete this item \''.htmlentities($row[$column]).'\'\n \'Cancel\' to stop, \'OK\' to delete.\')){return true;}return false;">Delete</a></span>';
                        }
                        $actions = $this->do_hook('row_actions',$actions);
?>
                <div class="row-actions">
<?php
                        if(isset($this->custom['actions_start'])&&function_exists("{$this->custom['actions_start']}"))
                            $this->custom['actions_start']($this,$row);
                        echo implode(' | ',$actions);
                        if(isset($this->custom['actions_end'])&&function_exists("{$this->custom['actions_end']}"))
                            $this->custom['actions_end']($this,$row);
?>
                </div>
<?php
                    }
                    else
                    {
?>
                <input type="hidden" name="order[]" value="<?php echo $row[$this->identifier]; ?>" />
<?php
                    }
?>
            </td>
<?php
                }
                elseif('date'==$attributes['type'])
                {
?>
            <td class="date column-date"><abbr title="<?php echo date('Y/m/d',strtotime($row[$column])); ?>"><?php echo date('Y/m/d',strtotime($row[$column])); ?></abbr></td>
<?php
                }
                elseif('time'==$attributes['type'])
                {
?>
            <td class="date column-date"><abbr title="<?php echo date('g:i:s A',strtotime($row[$column])); ?>"><?php echo date('g:i:s A',strtotime($row[$column])); ?></abbr></td>
<?php
                }
                elseif('datetime'==$attributes['type'])
                {
?>
            <td class="date column-date"><abbr title="<?php echo date('Y/m/d g:i:s A',strtotime($row[$column])); ?>"><?php echo date('Y/m/d g:i:s A',strtotime($row[$column])); ?></abbr></td>
<?php
                }
                elseif('related'==$attributes['type']&&false!==$attributes['related'])
                {
                    $column_data = array();
                    if(!is_array($attributes['related']))
                    {
                        $related = $wpdb->get_results('SELECT id,`'.$attributes['related_field'].'` FROM '.$attributes['related'].(!empty($attributes['related_sql'])?' '.$attributes['related_sql']:''));
                        $selected_options = explode(',',$row[$column]);
                        foreach($related as $option)
                            if(in_array($option->id,$selected_options))
                                $column_data[$option->id] = $option->$attributes['related_field'];
                    }
                    else
                    {
                        $related = $attributes['related'];
                        $selected_options = explode(',',$row[$column]);
                        foreach($related as $option_id=>$option)
                            if(in_array($option_id,$selected_options))
                                $column_data[$option_id] = $option;
                    }
?>
            <td class="author column-author"><?php echo implode(', ,',$column_data); ?></td>
<?php
                }
                elseif($attributes['type']=='bool')
                {
?>
            <td class="author column-author"><?php echo ($row[$column]==1?'Yes':'No'); ?></td>
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
<?php
        if($reorder==1&&false!==$this->reorder)
        {
?>
</form>
<?php
        }
?>
<script type="text/javascript">
jQuery('table.widefat tbody tr:even').addClass('alternate');
<?php
        if($reorder==1&&false!==$this->reorder)
        {
?>
jQuery(document).ready(function(){
    jQuery(".sortable").sortable({axis: "y", handle: ".dragme"});
    jQuery(".sortable").bind('sortupdate', function(event, ui){
        jQuery('table.widefat tbody tr').removeClass('alternate');
        jQuery('table.widefat tbody tr:even').addClass('alternate');
    });
});
<?php
        }
?>
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
        $request_uri = $this->var_update(array('pg'=>''),array('limit','order','order_dir','search_query')).'&';
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
                echo ' <a href="'.$this->var_update(array('limit'=>$option),array('order','order_dir','search_query')).'">'.$option.'</a>';
        }
    }
}