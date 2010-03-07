<?php
global $wpdb;
if(!is_object($wpdb))
{
    ob_start();
    require_once(realpath('../../../wp-load.php'));
    ob_end_clean();
}
/**
 * Admin UI class for WordPress plugins
 *
 * Creates a UI for any plugn screens within WordPress
 * 
 * NOTE: If you are including this class in a plugin,
 * please rename it to avoid conflicts with other plugins
 *
 * @package Admin UI for Plugins
 *
 * @version 1.0.0
 * @author Scott Kingsley Clark
 * @link http://www.scottkclark.com/
 *
 * @param mixed $options
 */
class Search_Engine_Admin
{
    var $item = 'Item';
    var $items = 'Items';
    var $table = false;
    var $icon = false;

    var $add = true;
    var $view = false;
    var $edit = true;
    var $delete = true;
    var $save = true;

    var $custom = array();

    var $total = 0;
    var $columns = array();
    var $data = array();
    var $row = array();
    var $order_columns = array();
    var $search_columns = array();
    var $form_columns = array();
    var $view_columns = array();
    var $insert_id = 0;

    var $id = false;
    var $action = 'manage';
    var $do = false;
    var $search = false;
    var $page = 1;
    var $limit = 25;
    var $order = 'id';
    var $order_dir = 'DESC';

    function __construct ($options=false)
    {
        if(false!==$this->get_var('id'))
            $this->id = $_GET['id'];
        if(false!==$this->get_var('action',false,array('add','edit','view','delete','manage')))
            $this->action = $_GET['action'];
        if(false!==$this->get_var('do',false,array('save')))
            $this->do = $_GET['do'];
        if(false!==$this->get_var('search'))
            $this->search = $_GET['search'];
        if(false!==$this->get_var('pg'))
            $this->page = $_GET['pg'];
        if(false!==$this->get_var('limit'))
            $this->limit = $_GET['limit'];
        if(false!==$this->get_var('order'))
            $this->order = $_GET['order'];
        if(false!==$this->get_var('order_dir',false,array('ASC','DESC')))
            $this->order_dir = $_GET['order_dir'];
        if(false!==$options&&!empty($options))
        {
            if(!is_array($options))
                parse_str($options,$options);
            foreach($options as $option=>$value)
                $this->$option = $value;
        }
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
        if(isset($array[$index]))
        {
            if($allowed!==false)
            {
                if(is_array($allowed))
                {
                    if(!in_array($array[$index],$allowed))
                        return $default;
                }
                elseif($allowed!=$array[$index])
                    return $default;
            }
            return $array[$index];
        }
        else
            return $default;
    }
    function hidden_vars ()
    {
        foreach($_GET as $k=>$v)
        {
?>
<input type="hidden" name="<?php echo $k; ?>" value="<?php echo $v; ?>" />
<?php
        }
    }
    function var_update($array=false,$allowed=false,$url=false)
    {
        $excluded = array('do','id','pg','search','order','order_dir','limit','action');
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
        if($url===false)
            $url = '';
        else
        {
            $url = explode('?',$_SERVER['REQUEST_URI']);
            $url = explode('#',$url[0]);
            $url = $url[0];
        }
        return $url.'?'.http_build_query($get);
    }
    function message ($msg)
    {
?>
	<div id="message" class="updated fade"><p><?php echo $msg; ?></p></div>
<?php
    }
    function go ()
    {
        if($this->action=='add'&&$this->add)
            $this->add();
        elseif($this->action=='edit'&&$this->edit)
            $this->edit();
        elseif($this->action=='delete'&&$this->delete)
            $this->delete();
        elseif($this->do=='save'&&$this->save)
            $this->save();
        elseif($this->do=='create'&&$this->save)
            $this->save(1);
        elseif($this->action=='view'&&$this->view)
            $this->view();
        else
            $this->manage();
    }
    function add ()
    {
        if(isset($this->custom['add'])&&function_exists("{$this->custom['add']}"))
            $this->custom['add']($this,1);
        if($this->do=='save'&&isset($_POST)&&!empty($_POST))
        {
            $this->save(1);
            $this->manage();
            return;
        }
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32"<?php if($this->icon!==false){ ?> style="background-position:0 0;background-image:url(<?php echo $this->icon; ?>);"<?php } ?>><br /></div>
    <h2>Add New <?php echo $this->item; ?> <small>(<a href="<?php echo $this->var_update(array('action'=>'manage','id'=>'')); ?>">&laquo; Back to Manage</a>)</small></h2>
<?php $this->form(1); ?>
</div>
<?php
    }
    function edit ()
    {
        if(isset($this->custom['edit'])&&function_exists("{$this->custom['edit']}"))
            $this->custom['edit']($this);
        if($this->do=='save'&&isset($_POST)&&!empty($_POST))
            $this->save();
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32"<?php if($this->icon!==false){ ?> style="background-position:0 0;background-image:url(<?php echo $this->icon; ?>);"<?php } ?>><br /></div>
    <h2>Edit <?php echo $this->item; ?> <small>(<a href="<?php echo $this->var_update(array('action'=>'manage','id'=>'')); ?>">&laquo; Back to Manage</a>)</small></h2>
<?php $this->form(); ?>
</div>
<?php
    }
    function form ($create=0)
    {
        if(isset($this->custom['form'])&&function_exists("{$this->custom['form']}"))
            return $this->custom['form']($this,$create);
        if($this->table===false)
            return $this->message('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        global $wpdb;
        if(empty($this->form_columns))
            $this->form_columns = $this->columns;
        $submit = 'Add '.$this->item;
        $id = '';
        if($create==0)
        {
            if(empty($this->row))
                $this->get_row();
            if(empty($this->row))
                return $this->message("<strong>Error:</strong> $this->item not found.");
            $submit = 'Save Changes';
            $id = $this->row['id'];
        }
?>
    <form method="post" action="<?php echo $this->var_update(array('action'=>'manage','do'=>'save','id'=>$id)); ?>">
        <table class="form-table">
<?php
        // available options
        // type = date (data validation as datetime)
        // type = text / other (single line text box)
        // type = desc (textarea)
        // type = number (data validation as int float)
        // type = decimal (data validation as decimal)
        // type = password (single line password box)
        // type = bool (single line password box)
        // readonly = true (shows as text)
        // update = if type=date then use current timestamp when saving (even if readonly)
        // display = false (doesn't show on form, but can be saved)
        foreach($this->form_columns as $column=>$attributes)
        {
            if(!is_array($attributes))
            {
                $column = $attributes;
                $attributes = array('type'=>'text');
            }
            if(!isset($attributes['label']))
                $attributes['label'] = ucwords(str_replace('_',' ',$column));
            if(!isset($this->row[$column]))
                $this->row[$column] = '';
            if($attributes['type']=='bool')
                $selected = ($this->row[$column]==1?' CHECKED':'');
            if(!isset($attributes['readonly']))
                $attributes['readonly'] = false;
            if(!isset($attributes['update']))
                $attributes['update'] = false;
            if(!isset($attributes['display']))
                $attributes['display'] = true;
            if(!isset($attributes['custom_input']))
                $attributes['custom_input'] = false;
            if(!isset($attributes['custom_form_display']))
                $attributes['custom_form_display'] = false;
            if(!isset($attributes['comments']))
                $attributes['comments'] = '';
            if($attributes['display']===false)
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
            }
            if($attributes['custom_input']!==false&&function_exists("{$attributes['custom_input']}"))
            {
                $attributes['custom_input']($column,$attributes,$this);
?>
        </td>
    </tr>
<?php
                continue;
            }
            if($attributes['custom_form_display']!==false&&function_exists("{$attributes['custom_form_display']}"))
            {
                $this->row[$column] = $attributes['custom_form_display']($column,$attributes,$this);
            }
            if($attributes['readonly']===true)
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
            <input type="password" name="<?php echo $column; ?>" id="admin_ui_<?php echo $column; ?>" value="<?php echo $this->row[$column]; ?>" />
<?php
                }
                elseif($attributes['type']=='desc')
                {
?>
            <textarea name="<?php echo $column; ?>" id="admin_ui_<?php echo $column; ?>" rows="10" cols="50"><?php echo $this->row[$column]; ?></textarea>
<?php
                }
                else
                {
?>
            <input type="text" name="<?php echo $column; ?>" id="admin_ui_<?php echo $column; ?>" value="<?php echo $this->row[$column]; ?>" />
<?php
                }
            }
            if(!empty($attributes['comments'])&&empty($attributes['comments_top']))
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
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="<?php echo $submit; ?>" />
        </p>
    </form>
<?php
    }
    function view ()
    {
        if(isset($this->custom['view'])&&function_exists("{$this->custom['view']}"))
            return $this->custom['view']($this);
        if($this->table===false)
            return $this->message('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        global $wpdb;
        if(empty($this->form_columns))
            $this->form_columns = $this->columns;
        if(empty($this->row))
            $this->get_row();
        if(empty($this->row))
            return $this->message("<strong>Error:</strong> $this->item not found.");
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32"<?php if($this->icon!==false){ ?> style="background-position:0 0;background-image:url(<?php echo $this->icon; ?>);"<?php } ?>><br /></div>
    <h2>View <?php echo $this->item; ?> <small>(<a href="<?php echo $this->var_update(array('action'=>'manage','id'=>'')); ?>">&laquo; Back to Manage</a>)</small></h2>
    <table class="form-table">
<?php
        // available options
        // type = date (data validation as datetime)
        // type = text / other (single line text box)
        // type = desc (textarea)
        // type = number (data validation as int float)
        // type = decimal (data validation as decimal)
        // type = password (single line password box)
        // type = bool (single line password box)
        // readonly = true (shows as text)
        // update = if type=date then use current timestamp when saving (even if readonly)
        // display = false (doesn't show on form, but can be saved)
        foreach($this->form_columns as $column=>$attributes)
        {
            if(!is_array($attributes))
            {
                $column = $attributes;
                $attributes = array('type'=>'text');
            }
            if(!isset($attributes['label']))
                $attributes['label'] = ucwords(str_replace('_',' ',$column));
            if(!isset($this->row[$column]))
                $this->row[$column] = '';
            if($attributes['type']=='bool')
                $selected = ($this->row[$column]==1?' CHECKED':'');
            if(!isset($attributes['readonly']))
                $attributes['readonly'] = false;
            if(!isset($attributes['update']))
                $attributes['update'] = false;
            if(!isset($attributes['display']))
                $attributes['display'] = true;
            if(!isset($attributes['custom_view']))
                $attributes['custom_view'] = false;
            if(!isset($attributes['comments']))
                $attributes['comments'] = '';
            if($attributes['display']===false)
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
            }
            if($attributes['custom_view']!==false&&function_exists("{$attributes['custom_view']}"))
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
?>
            <div id="admin_ui_<?php echo $column; ?>"><?php echo $this->row[$column]; ?></div>
<?php
            if(!empty($attributes['comments'])&&empty($attributes['comments_top']))
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
    function delete ()
    {
        if(isset($this->custom['delete'])&&function_exists("{$this->custom['delete']}"))
            return $this->custom['delete']($this);
        if($this->table===false)
            return $this->message('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        global $wpdb;
        $check = $wpdb->query($wpdb->prepare("DELETE FROM $this->table WHERE `id`=%f",array($this->id)));
        if($check)
            $this->message("<strong>Deleted:</strong> $this->item has been deleted.");
        else
            $this->message("<strong>Error:</strong> $this->item has not been deleted.");
        $this->manage();
    }
    function save ($create=0)
    {
        if(isset($this->custom['save'])&&function_exists("{$this->custom['save']}"))
            return $this->custom['save']($this);
        global $wpdb;
        $action = 'saved';
        if($create==1)
            $action = 'created';

        // available options
        // type = date (data validation as datetime)
        // type = text / other (single line text box)
        // type = desc (textarea)
        // type = number (data validation as int float)
        // type = decimal (data validation as decimal)
        // type = password (single line password box)
        // type = bool (single line password box)
        // readonly = true (shows as text)
        // update = if type=date then use current timestamp when saving (even if readonly)
        // display = false (doesn't show on form, but can be saved)
        $column_sql = array();
        $values = array();
        foreach($this->form_columns as $column=>$attributes)
        {
            $vartype = '%s';
            if(!is_array($attributes))
            {
                $column = $attributes;
                $attributes = array('type'=>'text');
            }
            if(!isset($attributes['label']))
                $attributes['label'] = ucwords(str_replace('_',' ',$column));
            if($attributes['type']=='bool')
                $selected = ($_POST[$column]==1?1:0);
            if($attributes['readonly']===false&&$attributes['type']!='date')
                continue;
            if(!isset($attributes['update']))
                $attributes['update'] = false;
            if(!isset($attributes['display']))
                $attributes['display'] = true;
            if(!isset($attributes['comments']))
                $attributes['comments'] = '';
            if($attributes['display']===false&&$attributes['type']!='date')
                continue;

            if($attributes['type']=='date')
            {
                if($attributes['update'])
                    $value = date("Y-m-d H:i:s");
                else
                    $value = date("Y-m-d H:i:s",strtotime($_POST[$column]));
            }
            else
            {
                if($attributes['type']=='bool')
                {
                    $vartype = '%f';
                    $value = 0;
                    if(isset($_POST[$column]))
                        $value = 1;
                }
                elseif($attributes['type']=='number')
                {
                    $vartype = '%f';
                    $value = number_format($_POST[$column],0,'','');
                }
                elseif($attributes['type']=='decimal')
                {
                    $vartype = '%d';
                    $value = number_format($_POST[$column],2,'.','');
                }
                else
                    $value = $_POST[$column];
            }
            if(isset($attributes['custom_save'])&&function_exists("{$attributes['custom_save']}"))
            {
                $value = $attributes['custom_save']($value,$column,$attributes,$this);
            }
            $column_sql[] = "`$column`=$vartype";
            $values[] = $value;
        }
        $column_sql = implode(',',$column_sql);
        if($create==0)
        {
            $this->insert_id = $this->id;
            $values[] = $this->id;
            $check = $wpdb->query($wpdb->prepare("UPDATE $this->table SET $column_sql WHERE id=%f",$values));
        }
        else
        {
            $check = $wpdb->query($wpdb->prepare("INSERT INTO $this->table SET $column_sql",$values));
        }
        if($check)
        {
            if($this->insert_id==0)
                $this->insert_id = $wpdb->insert_id;
            $this->message('<strong>Success!</strong> '.$this->item.' '.$action.' successfully.');
        }
        else
            $this->message('<strong>Error</strong> '.$this->item.' has not been '.$action.'.');
    }
    function get_row ()
    {
        if(isset($this->custom['row'])&&function_exists("{$this->custom['row']}"))
            return $this->custom['row']($this);
        if($this->table===false)
            return $this->message('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        if($this->id===false)
            return $this->message('<strong>Error:</strong> Invalid Configuration - Missing "id" definition.');
        global $wpdb;
        $sql = "SELECT * FROM $this->table WHERE `id`=".$wpdb->_real_escape($this->id);
        $row = @current($wpdb->get_results($sql,ARRAY_A));
        if(!empty($row))
            $this->row = $row;
    }
    function get_data ()
    {
        if(isset($this->custom['data'])&&function_exists("{$this->custom['data']}"))
            return $this->custom['data']($this);
        if($this->table===false)
            return $this->message('<strong>Error:</strong> Invalid Configuration - Missing "table" definition.');
        global $wpdb;
        $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM $this->table";
        if(false!==$this->search)
        {
            $sql .= " WHERE ";
            if(empty($this->search_columns))
                $this->search_columns = $this->columns;
            $and = false;
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
                $sql .= "`$column` LIKE '%".$wpdb->_real_escape($this->search)."%'";
            }
        }
        $sql .= ' ORDER BY ';
        if(isset($this->columns[$this->order])||in_array($this->order,$this->columns))
            $sql .= $this->order.' '.$this->order_dir;
        else
            $sql .= 'id';
        $start = ($this->page-1)*$this->limit;
        $end = ($this->page-1)*$this->limit+$this->limit;
        $sql .= " LIMIT $start,$end";
        $this->data = $wpdb->get_results($sql,ARRAY_A);
        $total = @current($wpdb->get_col("SELECT FOUND_ROWS()"));
        if(is_numeric($total))
            $this->total = $total;
    }
    function manage ()
    {
        if(isset($this->custom['manage'])&&function_exists("{$this->custom['manage']}"))
            return $this->custom['manage']($this);
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32"<?php if($this->icon!==false){ ?> style="background-position:0 0;background-image:url(<?php echo $this->icon; ?>);"<?php } ?>><br /></div>
    <h2>Manage <?php echo $this->items; ?></h2>
<?php
        if(isset($this->custom['header'])&&function_exists("{$this->custom['header']}"))
            echo $this->custom['header']($this);
        if(empty($this->data))
            $this->get_data();
        if(!empty($this->data)||!empty($this->search))
        {
?>
    <form id="posts-filter" action="" method="get">
        <p class="search-box">
<?php $this->hidden_vars(); ?>
            <label class="screen-reader-text" for="page-search-input">Search:</label>
<?php
            if(false!==$this->search)
            {
?>
            <small>[<a href="<?php echo $this->var_update(array('search'=>''),array('order','order_dir','limit')); ?>">Reset Filters</a>]</small>
<?php
            }
?>
            <input type="text" name="search" id="page-search-input" value="<?php echo $this->search; ?>" />
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
        if(!empty($this->data))
        {
?>
        <div class="tablenav-pages">
            Show per page:<?php $this->limit(); ?> &nbsp;|&nbsp;
<?php $this->pagination(); ?>
        </div>
<?php
        }
        if($this->add)
        {
?>
        <div class="alignleft actions">
            <input type="button" value="Add New <?php echo $this->item; ?>" class="button" onclick="document.location='<?php echo $this->var_update(array('action'=>'add')); ?>'" />
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
            if(!is_array($attributes))
            {
                $column = $attributes;
                $attributes = array();
            }
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
                if(isset($attributes['custom_display'])&&function_exists("{$attributes['custom_display']}"))
                {
                    $row[$column] = $attributes['custom_display']($row[$column],$row,$this);
                }
                if(isset($attributes['custom_relate']))
                {
                    global $wpdb;
                    $table = $attributes['custom_relate'];
                    $on = 'id';
                    $is = $row['id'];
                    $what = array('name');
                    if(is_array($table))
                    {
                        if(isset($table['on']))
                            $on = $wpdb->_real_escape($table['on']);
                        if(isset($table['is'])&&isset($row[$table['is']]))
                            $is = $wpdb->_real_escape($row[$table['is']]);
                        if(isset($table['what']))
                        {
                            $what = array();
                            if(is_array($table['what']))
                            {
                                foreach($table['what'] as $wha)
                                {
                                    $what[] = $wpdb->_real_escape($wha);
                                }
                            }
                            else
                            {
                                $what[] = $wpdb->_real_escape($table['what']);
                            }
                        }
                        if(isset($table['table']))
                            $table = $table['table'];
                    }
                    $table = $wpdb->_real_escape($table);
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
        if(isset($this->custom['pagination'])&&function_exists("{$this->custom['pagination']}"))
            return $this->custom['pagination']($this);
        $page = $this->page;
        $rows_per_page = $this->limit;
        $total_rows = $this->total;
        $total_pages = ceil($total_rows / $rows_per_page);
        $request_uri = $this->var_update(array('pg'=>''),array('limit','order','order_dir','search')).'&';
        $begin = ($rows_per_page*$page)-($rows_per_page-1);
        $end = ($total_pages==$page?$total_rows:($rows_per_page*$page));<?php if($total_rows<1){ echo 0; } else { echo $begin; ?>&#8211;<?php echo $end; } ?>
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
        if(isset($this->custom['limit'])&&function_exists("{$this->custom['limit']}"))
            return $this->custom['limit']($this);
        if($options===false||!is_array($options)||empty($options))
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