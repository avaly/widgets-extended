<?php
/*
Plugin Name: Widgets Extended
Plugin URI: http://github.com/avaly/widgets-extended
Description: Extend widgets with various options
Version: 1.0
Author: Valentin Agachi
Author URI: http://agachi.name
Based on: http://wordpress.org/extend/plugins/widget-logic/
License: GPL2
*/



class widgets_extended
{
	var $options_key = 'widgets_extended';

	function __construct()
	{
		// admin
		add_action('sidebar_admin_setup', array($this, 'sidebar_admin_setup'));

		// site
		add_action('wp_head', array($this, 'redirect_callback'));
	}

	function get_options()
	{
		if ((!$options = get_option($this->options_key)) || !is_array($options)) $options = array();
		return $options;
	}


	
	// admin

	function sidebar_admin_setup()
	{
		global $wp_registered_widgets, $wp_registered_widget_controls;

		$options = $this->get_options();

		if ('post' == strtolower($_SERVER['REQUEST_METHOD']))
		{
			foreach ((array)$_POST['widget-id'] as $widget_number => $widget_id )
			{
				if (isset($_POST[$widget_id.'-we_logic']))
					$options[$widget_id]['logic'] = $_POST[$widget_id.'-we_logic'];

				if (isset($_POST[$widget_id.'-we_class']))
					$options[$widget_id]['class'] = $_POST[$widget_id.'-we_class'];
			}
			
			$optionsNew = array_merge(array_keys($wp_registered_widgets), array_values((array)$_POST['widget-id']));

			foreach (array_keys($options) as $key)
				if (!in_array($key, $optionsNew))
					unset($options[$key]);

			update_option($this->options_key, $options);
		}
		
		foreach ( $wp_registered_widgets as $id => $widget )
		{
			if (!$wp_registered_widget_controls[$id])
				wp_register_widget_control($id, $widget['name'], array($this, 'empty_control'));
					
			if (!array_key_exists(0, $wp_registered_widget_controls[$id]['params']) || 
				is_array($wp_registered_widget_controls[$id]['params'][0]))
				$wp_registered_widget_controls[$id]['params'][0]['id_we'] = $id;

			$wp_registered_widget_controls[$id]['callback_we'] = $wp_registered_widget_controls[$id]['callback'];
			$wp_registered_widget_controls[$id]['callback'] = array($this, 'extra_control');
		}
	}

	function empty_control() {}

	function extra_control()
	{
		global $wp_registered_widget_controls;

		$params = func_get_args();

		$id = (is_array($params[0])) ? $params[0]['id_we'] : array_pop($params);	
		$id_disp = $id;

		$options = $this->get_options();
		
		$callback = $wp_registered_widget_controls[$id]['callback_we'];
		if (is_callable($callback))
			call_user_func_array($callback, $params);

		$values = (is_array($options[$id]) ? $options[$id] : array());
		foreach ($values as $k => $v)
			$values[$k] = htmlspecialchars(stripslashes($v), ENT_QUOTES);

		// dealing with multiple widgets - get the number. if -1 this is the 'template' for the admin interface
		if (is_array($params[0]) && isset($params[0]['number'])) $number = $params[0]['number'];
		if ($number == -1) {
			$number = "%i%";
			$value = "";
		}
		if (isset($number)) 
			$id_disp = $wp_registered_widget_controls[$id]['id_base'].'-'.$number;

		echo '<p><label for="'.$id_disp.'-we_logic">Widget Logic <a href="http://codex.wordpress.org/Conditional_Tags" target="_blank">?</a></label> <input type="text" name="'.$id_disp.'-we_logic" id="'.$id_disp.'-we_logic" value="'.$values['logic'].'" size="15"/></p>';
		echo '<p><label for="'.$id_disp.'-we_class">Extra class</label> <input type="text" name="'.$id_disp.'-we_class" id="'.$id_disp.'-we_class" value="'.$values['class'].'" size="15"/></p>';
	}



	// site

	function redirect_callback()
	{
		global $wp_registered_widgets;
		foreach ($wp_registered_widgets as $id => $widget)
		{
			if (!$wp_registered_widgets[$id]['callback_we'])
			{
				array_push($wp_registered_widgets[$id]['params'], $id);
				$wp_registered_widgets[$id]['callback_we'] = $wp_registered_widgets[$id]['callback'];
				$wp_registered_widgets[$id]['callback'] = array($this, 'redirected_callback');
			}
		}
	}

	function redirected_callback()
	{
		global $wp_registered_widgets, $wp_reset_query_is_done;

		$params = func_get_args();
		$id = array_pop($params);
		$callback = $wp_registered_widgets[$id]['callback_we'];
		
		$options = $this->get_options();

		$values = (is_array($options[$id]) ? $options[$id] : array());
		foreach ($values as $k => $v)
			$values[$k] = stripslashes($v);

		$values['logic'] = (strlen($values['logic']) ? $values['logic'] : 'true');
		$values['logic'] = (stristr($values['logic'], 'return')) ? $values['logic'] : 'return ('.$values['logic'].');';

		$enabled = (eval($values['logic']) && is_callable($callback));
		if ($enabled)
		{	
			ob_start();
			call_user_func_array($callback, $params);
			$content = ob_get_clean();

			if (!empty($values['class']))
			{
				$content = preg_replace('~^\s*(<[^\s>]+ [^>]*)class="([^"]+)"([^>]*>)~i', '$1class="$2 '.$values['class'].'"$3', $content);
			}

			echo $content;
		}
	}

}

$widgets_extended = new widgets_extended();


// no php end tag