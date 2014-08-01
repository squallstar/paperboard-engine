<?php
/**
 * Manage controller
 *
 * @package     Hhvm
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Manage extends Cronycle_Controller
{

	public function __construct()
	{
		parent::__construct();

		//if (ENVIRONMENT == 'production') show_404();

		$this->db = collection();
	}

	public function mongo()
	{
		redirect('http://'.$_SERVER['HTTP_HOST'].':28025');
	}

	public function index()
	{
		echo 'alive';
	}

	// public function schema()
	// {
	// 	$this->view->render('misc/schema');
	// }

	public function recreate()
	{
		//1.clear
		$this->db->drop();

		//2.collections and indexes

		//2.1 users
		$users = new MongoCollection($this->db, 'users');
		$users->ensureIndex(array('email' => 1), array('unique' => true));
		$users->ensureIndex(array('password' => 1));
		$users->ensureIndex(array('auth_token' => 1), array('unique' => true));

		//$users->ensureIndex(array('username' => 1), array('unique' => TRUE));
		//$users->ensureIndex(array('name' => 1)); //To order users
		//$users->ensureIndex(array('optin' => 1));
		//$users->ensureIndex(array('twitter.id' => 1), array('unique' => TRUE));

		// //2.2 projects
		// $projects = new MongoCollection($this->db, 'projects');
		// $projects->ensureIndex(array('name' => 1));
		// $projects->ensureIndex(array('users' => 1));
		// $projects->ensureIndex(array('ispublic' => 1));
		// $projects->ensureIndex(array('datecreate' => -1));

		// //2.3 discussions
		// $discussions = new MongoCollection($this->db, 'discussions');
		// $discussions->ensureIndex(array('project' => 1));
		// $discussions->ensureIndex(array('dateupdate' => -1));
		// $discussions->ensureIndex(array('deleted' => 1));

		// //2.4 todo lists
		// $todolists = new MongoCollection($this->db, 'todolists');
		// $todolists->ensureIndex(array('name' => 1));
		// $discussions->ensureIndex(array('dateupdate' => -1));
		// $todolists->ensureIndex(array('project' => 1));
		// $todolists->ensureIndex(array('items.completed' => 1));

		// //2.5 notifications
		// $notifications = new MongoCollection($this->db, 'notifications');
		// $notifications->ensureIndex(array('date' => -1));
		// $notifications->ensureIndex(array('project' => 1));
		// $notifications->ensureIndex(array('user.username' => 1));
		// //This removed duplicates comments (same minute)
		// $notifications->ensureIndex(
		// 	//array('user.username' => 1, 'significant_date' => 1, 'discussion.id' => 1, 'action' => 1, 'todolist.item.due' => 1),
		// 	array('unique' => true)
		// );

		// //2.6 files
		// $files = new MongoCollection($this->db, 'files');
		// $files->ensureIndex(array('owner' => 1));
		// $files->ensureIndex(array('linked' => 1));

		// //-- mongodb end

		echo 'done';

	}


	// public function populate()
	// {
	// 	ini_set("memory_limit","128M");

	// 	// ********************************************************************

	// 	$n_project 		= 20;
	// 	$n_discussions 	= 20; //and todo lists
	// 	$n_comments		= 20;

	// 	// ********************************************************************


	// 	$this->user->check_login();
	// 	$this->load->model('model_projects', 'projects');

	// 	for ($i=0; $i < $n_project; $i++) {
	// 		$project = new Project(array(
	// 			'name' => 'Sample Project ' . $i,
	// 			'owner'	=> $this->user->get('id'),
	// 			'ispublic' => (rand(0, 5) == 5 ? 1 : 0),
	// 			'users'	=> array(
	// 				$this->user->get('id')
	// 			)
	// 		));

	// 		if ($project->save()) {
	// 			for ($j=0; $j < $n_discussions; $j++) {

	// 				$n_comments = rand(2,15);

	// 				//2. Add discussion
	// 				$form_data = array(
	// 					'subject' 		=> 'Sample Discussion ' . $j,
	// 					'body'			=> 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus varius odio a mi luctus vitae ultricies enim viverra. Ut imperdiet rutrum porttitor. Fusce sagittis pretium ullamcorper. In nibh magna, consectetur nec ullamcorper id, scelerisque vel purus. Donec a arcu nulla, a aliquet leo. Etiam magna risus, fringilla vitae lacinia quis, tincidunt quis diam. Aenean iaculis posuere odio vel ullamcorper.
	// 										Sed pharetra rutrum bibendum. Mauris rutrum porttitor fringilla. Suspendisse ut scelerisque urna. Donec eu lacus quis orci auctor tempus eu ac tortor. Aenean sollicitudin, lacus ut accumsan ornare, velit diam porttitor lacus, volutpat ultrices nunc odio ut nibh. Cras et nulla et orci iaculis tempus vitae ac lectus. Sed vel lacus leo, non accumsan tellus.
	// 										Nam condimentum pharetra orci, eu laoreet nisl fringilla ac. Donec vestibulum tincidunt ante sed sagittis. In magna augue, congue vel dictum non, gravida nec massa. Nunc nisl est, cursus et mollis non, consequat vel sapien. Morbi libero dui, bibendum nec suscipit quis, vulputate ac urna. In hac habitasse platea dictumst. Maecenas lectus ante, pulvinar ut porttitor sit amet, feugiat vitae diam. Suspendisse eu purus nec tellus rutrum dignissim. Duis eget interdum neque. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Sed ultricies venenatis metus, id cursus sapien iaculis in. Nam convallis lacinia purus et sodales. In bibendum placerat nisi molestie consectetur.
	// 										Quisque non neque eget urna condimentum vestibulum. Maecenas tempus sapien tellus, tincidunt pharetra leo. Sed ac posuere lacus. Nullam sodales est et purus lobortis pretium. Aenean tempus ornare tellus, sed ultrices magna mattis sed. Nulla orci lacus, interdum at ornare eu, dignissim at justo. Nam turpis nulla, tristique a malesuada id, volutpat sed purus. Quisque sit amet augue in turpis condimentum rutrum. Integer feugiat neque quis tortor condimentum id sodales quam sodales. Aenean fermentum venenatis consequat.
	// 										Quisque egestas elit ut arcu mattis dignissim. Donec id sollicitudin massa. Phasellus sed tellus odio. Mauris felis neque, dictum id convallis a, gravida sed sem. Vivamus eu leo mattis sem viverra sodales eu sit amet sapien. Maecenas interdum hendrerit odio. Quisque semper purus at ipsum laoreet tincidunt. Etiam interdum, dolor ut porta bibendum, sem augue dapibus nunc, eu porttitor libero augue non eros. Quisque ac libero sapien. Pellentesque scelerisque, mi non sodales interdum, tortor enim malesuada eros, ut facilisis sapien elit consectetur velit. Proin nec lobortis nibh.
	// 										-end-',
	// 					'owner'			=> array(
	// 						'id'		=> $this->user->get('id'),
	// 						'name'		=> $this->user->get('name'),
	// 						'avatar'	=> $this->user->get('img')
	// 					),
	// 					'can_comment'	=> (rand(0,4) == 4 ? 'anyone' : 'members'),
	// 					'ncomments'		=> $n_comments
	// 				);

	// 				$disc_id = $project->add_discussion($form_data);

	// 				for ($k=0; $k < $n_comments; $k++) {
	// 					//Comment

	// 					$comment = array(
	// 						'_id'			=> campid('c'),
	// 						'name'			=> $this->user->get('name'),
	// 						'user'			=> $this->user->get('id'),
	// 						'avatar'		=> $this->user->get('img'),
	// 						'message'		=> 'Nam condimentum pharetra orci, eu laoreet nisl fringilla ac. Donec vestibulum tincidunt ante sed sagittis.',
	// 						'datecreate'	=> time()
	// 					);

	// 					collection('discussions')->update(
	// 						array(
	// 							'_id' => $disc_id
	// 						),
	// 						array(
	// 							'$inc'	=> array(
	// 								'ncomments' => 1
	// 							),
	// 							'$set'	=> array(
	// 								'dateupdate' => time()
	// 							),
	// 							'$push' => array(
	// 								'comments' => $comment
	// 							)
	// 						)
	// 					);
	// 				}

	// 				//Add todo list
	// 				$list_data = array(
	// 					'name' 			=> 'Sample todolist ' . $j,
	// 					'description' 	=> 'Nam condimentum pharetra orci, eu laoreet nisl fringilla ac.',
	// 					'owner'			=> $this->user->get('id'),
	// 					'ndone'			=> 0,
	// 					'nopen'			=> $n_discussions
	// 				);

	// 				$list_id = $project->add_todolist($list_data);

	// 				$order = array();

	// 				for ($l=0; $l < $n_discussions; $l++) {
	// 					$todo_data = array(
	// 						'_id'		 => campid('t'),
	// 						'datecreate' => time(),
	// 						'owner'		 => $this->user->get('id'),
	// 						'content'	 => 'A sample things to finish, ' . $l,
	// 						'assigned'	 => false,
	// 						'due'		 => false,
	// 						'completed'	 => false,
	// 						'comments'	 => array(),
	// 						'ncomments'	 => rand(0,10)
	// 					);

	// 					$order[] = $todo_data['_id'];

	// 					for ($n=0; $n < $todo_data['ncomments']; $n++) {
	// 						$todo_data['comments'][] = array(
	// 							'_id'			=> campid('c'),
	// 							'name'			=> $this->user->get('name'),
	// 							'user'			=> $this->user->get('id'),
	// 							'avatar'		=> $this->user->get('img'),
	// 							'message'		=> 'Nam condimentum pharetra orci, eu laoreet nisl fringilla ac. Donec vestibulum tincidunt ante sed sagittis.',
	// 							'datecreate'	=> time()
	// 						);
	// 					}

	// 					$done = collection('todolists')->update(
	// 						array(
	// 							'_id'		=> $list_id
	// 						),
	// 						array(
	// 							'$push'		=> array(
	// 								'items'	=> $todo_data
	// 							)
	// 						)
	// 					);
	// 				}

	// 				collection('todolists')->update(
	// 					array(
	// 						'_id'		=> $list_id
	// 					),
	// 					array(
	// 						'$set'		=> array(
	// 							'order'	=> $order
	// 						)
	// 					)
	// 				);
	// 			}
	// 		}
	// 	}
	// 	redirect('projects');
	// }
}