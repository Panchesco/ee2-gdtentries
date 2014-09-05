<?php
/**
* Gdtentries Class
*
* @package ExpressionEngine
* @author Richard Whitmer/Godat Design, Inc.
* @copyright (c) 2014, Godat Design, Inc.
* @license
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
* @link http://godatdesign.com
* @since Version 2.9
*/
 
 // ------------------------------------------------------------------------

/**
 * Good at Entries Plugin
 *
 * @package			ExpressionEngine
 * @subpackage		third_party
 * @category		Plugin
 * @author			Richard Whitmer/Godat Design, Inc.
 * @copyright		Copyright (c) 2014, Godat Design, Inc.
 * @link			http://godatdesign.com
 */
  
 // ------------------------------------------------------------------------

	$plugin_info = array(
	    'pi_name'         => 'Good at Entries',
	    'pi_version'      => '1.0',
	    'pi_author'       => 'Richard Whitmer/Godat Design, Inc.',
	    'pi_author_url'   => 'http://godatdesign.com/',
	    'pi_description'  => '
	    Customized handling and sorting for returning lists of entries.
	    ',
	    'pi_usage'        => Gdtentries::usage()
	);
	

	class  Gdtentries {
			
			public	$site_id			= 1;
			public	$channel_name		= 'awards-database';
			public	$channel_id			= 8;
			public	$field_group		= 0;
			public	$status				= array('open');
			public	$order_by			= array('entry_date','title');
			public	$sort				= array();
			public	$total_rows			= 0;
			public	$query_strings		= FALSE;
			public	$tagdata;
			public	$base_url			= '';
			public	$display_pages		= TRUE;
			
			
			private	$title_params		= array(
													'title'	=>	NULL,
													'entry_id'	=>	NULL,
													'entry_date'	=>	NULL,
													'year'	=>	NULL,
													'month'	=>	NULL,
													'day'	=>	NULL
												);
			private	$field_data			= array();
			private	$field_params		= array();
			private	$where				= array();
			private $select				= array();
			
		
			public function __construct()
			{
				
				
				
				// Will we allow query strings?
				$this->query_strings	= ee()->TMPL->fetch_param('query_strings',FALSE);
				
				// Fetch  limit and offset.
				$this->limit	= ee()->TMPL->fetch_param('limit',1);
				$this->offset	= ee()->TMPL->fetch_param('offset',0);
				
				if(ee()->TMPL->fetch_param('display_pages'))
				{
					if(strtoupper(ee()->TMPL->fetch_param('display_pages'))=='FALSE')
					{
						$this->display_pages = FALSE;
					}	
				};
				
				// Fetch the channel name
				if(ee()->TMPL->fetch_param('channel_name'))
				{
					$this->channel_name	= ee()->TMPL->fetch_param('channel_name');
					
					// Set the channel_id property.
					$this->set_channel_id();
				}
				
				// Fetch the channel_id if it's been set.
				if(ee()->TMPL->fetch_param('channel_id'))
				{
					$this->channel_id	= ee()->TMPL->fetch_param('channel_id');
				}
				
				// How should query results be ordered?
				if(ee()->TMPL->fetch_param('order_by'))
				{
					$this->order_by	= explode('|',ee()->TMPL->fetch_param('order_by'));
				}
				
				// How should they bee sorted?
				if(ee()->TMPL->fetch_param('sort'))
				{
					$this->sort	= explode('|',ee()->TMPL->fetch_param('sort'));
				}
				
				// Set the field group.
				$this->set_field_group();
				
				// Set field_data.
				$this->set_field_data();

				
				// Channel Titles params
				foreach($this->title_params as $key=>$row)
				{
					if(ee()->TMPL->fetch_param($key))
					{
						$this->title_params[$key] = ee()->TMPL->fetch_param($key);
					}
				}
				
				// Now that we have the custom field names, look for additional parameters.
				$i = 0;
				foreach($this->field_data as $key=>$row)
				{
					
					// Look in the tag
					if(ee()->TMPL->fetch_param($row))
					{
						$param	= strtoupper(ee()->TMPL->fetch_param($row));
						
						if( ! in_array($param,array('',"NULL",NULL,"FALSE",FALSE)))
						{
							$this->field_params[$row] = ee()->TMPL->fetch_param($row);
						}
						
					}
					
											
					// Look in query string?
					if($this->query_strings !== FALSE)
					{
					
						if($i==0)
						{
							$this->base_url = ee()->functions->fetch_current_uri() . '?';
						}
						
						if(ee()->input->get($row,TRUE))
						{
						    $this->field_params[$row] = ee()->input->get($row,TRUE);
						    $this->base_url.= $row.'='.$this->field_params[$row].'&';
						}
						
						

					}
					
					$i++;	
				}
				
				if($this->query_strings !== FALSE)
				{
					
					if(ee()->input->get('limit'))
					{
							$this->limit	= ee()->input->get('limit');
							$this->base_url.= 'limit='.$this->limit.'&';
					}
						
					if(ee()->input->get('offset'))
					{
							$this->offset	= ee()->input->get('offset');
							$this->base_url.= 'offset='.$this->offset.'&';
					}
				}
				
				
				$this->base_url = trim($this->base_url,'&');
				
				// Set some properties.
				$this->set_select();
				$this->set_where();
				$this->set_total_rows();
				
			}
			
			// ------------------------------------------------------------------------
		
				 
				 public function rows()
				 {
				 
				 	if($this->total_rows > 0)
				  	{

				 		$data	= array();
				 		$this->set_select();
				 		$this->set_where();
				 		
				 		$this->select[]	=	'CONCAT(' . $this->total_rows . ') AS total_rows';

				 		ee()->db->from('channel_titles');
				 		ee()->db->select($this->select);
				 		ee()->db->where_in('channel_titles.status',$this->status);
				 		ee()->db->where($this->where);
				 		ee()->db->join('channel_data ','channel_data.entry_id = channel_titles.entry_id');
				  	
				 		foreach($this->order_by as $key=>$row)
				 		{
				  	
				  			if(isset($this->sort[$key]))
				  			{
					  		
					  			$sort = $this->sort[$key];
					  		
					  			} else {
					  		
						  			$sort = 'ASC';
						  			}
				  		
						  			ee()->db->order_by($row,$sort);

						  			}
				  	
						  			ee()->db->limit($this->limit,$this->offset);
				  	
						  			$query = ee()->db->get();
						  			$rows	= $query->result_array();
						  			$pagination = $this->paginate();
						  			
						  			$chunk	= $this->offset + $this->limit;
						  			
						  			foreach($rows as $key => $row)
						  			{
							  			$rows[$key]['pagination']	= $pagination;
							  			$rows[$key]['position']	= $this->offset + ($key+1);
							  			$rows[$key]['end']		= ($chunk <= $this->total_rows) ? $chunk : $this->total_rows;
						  			}

						 return  ee()->TMPL->parse_variables(ee()->TMPL->tagdata,$rows);
				  							  	
				  	} else {
					  	
					  	return ee()->TMPL->no_results;
				  	}	
				  	
			}
			
			
			/**
			 * Pagination...
			 */
			 public function paginate()
			 {
				 
				 
				ee()->load->library('pagination');

				$config['base_url'] = '/grants-and-awards/awards-db-table';
				$config['total_rows'] = $this->total_rows;
				$config['per_page']	= $this->limit;
				$config['num_links']	= 6;

				ee()->pagination->initialize($config);

				return ee()->pagination->create_links();
				
			 }
			

			
			// ------------------------------------------------------------------------

			/**
			 *	Return plugin usage documentation.
			 *	@return string
			 */
			public function usage()
			{
				
					ob_start();  ?>
					
					
					TAG PAIRS:
					----------------------------------------------------------------------------
					{exp:gdtentries:rows}
					
					
					REQUIRED PARAMETERS: 
					----------------------------------------------------------------------------
					{channel_name}
					
					
					OPTIONAL PARAMETERS: 
					----------------------------------------------------------------------------
					{channel_id}
					{entry_id}
					{title}
					{entry_date}
					{year}
					{month}
					{day}
					{order_by}		-	Pipe delimited list of vars to use as ORDER BY in query
					{sort}			-	Pipe delimited list of ASC or DESC. Will work in tandem w/{order_by} values, but not required. Default is ASC.
					{limit}			-	Default is 1
					{offset}		-	Default is 0
					{query_strings}		-	Default is FALSE
					{custom_field}		-	Use any custom field name.
					{display_pages}		-	Show page numbers in pagination links? Default is TRUE.
					
					
					VARIABLES: 
					----------------------------------------------------------------------------
					{entry_id}
					{title}
					{entry_date}
					{year}
					{month}
					{day}	
					{custom_field}
					{count}				
					{total_results}
					{no_results}	
					{total_rows}		- Total, unlimited set of rows
					{position}		- Position number of row in all rows
					{end}			- Last position number in current page of results
					

					<?php
					 $buffer = ob_get_contents();
					 ob_end_clean();
					
					return $buffer;
					
			}
			
			/**
			 *	Use the channel_name to set the channel_id property.
			 */
			 private function set_channel_id()
			 {
				 if($this->channel_name !== '')
				     {
					     $query = ee()->db
					     			->select('channel_id')
					     			->where('channel_name',$this->channel_name)
					     			->limit(1)
					     			->get('channels');
					     			
					     if($query->num_rows()==1)
					     {
						     $this->channel_id = $query->row()->channel_id;
					     }
				     }
			 }
			 
			 
			 // ------------------------------------------------------------------------
			
			
			/**
			 *	Use the channel_id to set the field group property.
			 */
			 private function set_field_group()
			 {
				 
					     $query = ee()->db
					     			->select('field_group')
					     			->where('channel_name',$this->channel_name)
					     			->limit(1)
					     			->get('channels');
					     			
					     if($query->num_rows()==1)
					     {
						     $this->field_group = $query->row()->field_group;
					     }

			 }
			 
			 
			 // ------------------------------------------------------------------------
			 
			 /**
			  *	Custom field data...
			  * @return array
			  */
			  private function set_field_data()
			  {
			  
			  
			  	$where	= array(
			  					'site_id'	=>	$this->site_id,
			  					'group_id'	=>	$this->field_group
			  					);
			  	
			  	$query = ee()->db
			  				->select('field_id,field_name')
			  				->where($where)
			  				->get('channel_fields');
			  
			  
			  	if($query->num_rows()>0)
			  	{
				  	foreach($query->result() as $key=>$row)
				  	{
					  	$this->field_data['field_id_'.$row->field_id] = $row->field_name;
				  	}
			  	}
			  	
			  }
			  
			  
			  // ------------------------------------------------------------------------
			  
			  /**
			   *	 Write the entry titles query.
			   */
			   private function set_select()
			   {
			   		$this->select	= array();
				  
				   foreach($this->title_params as $key=>$row)
				   {
				   	
				   	$this->select[]	= 'channel_titles.' . $key;
				   	
				   }
	
				   foreach($this->field_data as $key=>$row)
				   {
				   	
				   	$this->select[]	= 'channel_data.' . $key . ' AS ' . $row;
				   	
				   }
				   
				  }
				  
				  // ------------------------------------------------------------------------
				  
				  /**
				   * Set where
				   */
				   private function set_where()
				   {
				   
				   $this->where = array(
				   							'channel_titles.site_id'=>$this->site_id,
				   							'channel_titles.channel_id'=>$this->channel_id
				   							);
				   
				   
				   $field_ids = array_flip($this->field_data);
				   
				    foreach($this->title_params as $key => $row)
					{
					  if($row !== NULL)
					  {
						   $this->where['channel_titles.'.$key] = $row;
						  
					  }
					 
					}

				   
					foreach($this->field_params as $key => $row)
					{
						
						 $this->where['channel_data.'.$field_ids[$key]] = $row;
					}
					  
				  }
				  
				 // ------------------------------------------------------------------------ 
				  
				  /**
				   *	Set total results.
				   */
				   private function set_total_rows()
				   {

					   $query = ee()->db
				  			->from('channel_titles')
				  			->select('channel_titles.entry_id')
				  			->where_in('status',$this->status)
				  			->where($this->where)
				  			->join('channel_data ','channel_data.entry_id = channel_titles.entry_id')
				  			->get();
				  			
				 
				  			$this->total_rows	= $query->num_rows();
				 
				 }
				  			

				 // ------------------------------------------------------------------------
			

		
	}
/* End of file pi.gdtentries.php */
/* Location: ./system/expressionengine/third_party/gdtentries/pi.gdtentries.php */
