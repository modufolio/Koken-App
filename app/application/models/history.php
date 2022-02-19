<?php

class History extends DataMapper {
	
	var $table = 'history';
	
	var $has_one = array(
		'user'
	);
	
	// Override save method for delayed queries
	function save($user_id = false) 
	{
		if ($user_id)
		{
			$message = serialize($this->message);
			$this->where('user_id', $user_id)->order_by('created_on DESC')->limit(1)->get();
			if ($this->message == $message) 
			{
				// Same as last history, so just update timestamp instead of dup'ing
				$this->db->query("UPDATE $this->table SET created_on = " . time() . " WHERE id = {$this->id}");
			}
			else 
			{
				$this->db->query("INSERT INTO $this->table VALUES(NULL, {$user_id}, '{$message}', " . time() . ")");
			}
		}
	}
}

/* End of file history.php */
/* Location: ./application/models/history.php */