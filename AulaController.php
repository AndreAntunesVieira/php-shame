<?php
use Intercom\IntercomBasicAuthClient;
class AulaController extends BaseController
{
	public $category;
	public $course;
	public $lesson;
	public $module;
	private $lesson_video;
	private $lesson_exercise;
	private $lesson_text;
	private $alternatives;
	private $alternative_selected;
	private $progress;
	private $view_variables = array();

	private $special_lesson_vars;

	public function fromItemMain(){
		return $this->getAula($this->lesson->category_slug, $this->lesson->course_slug, $this->lesson->module_slug, $this->lesson->slug, $this->extra1);
	}

	public function getAula($category_slug, $course_slug, $module_slug, $lesson_slug, $exercise_number = null)
	{
		Intended::saveCurrentUrl();
		if(!$this->getCategory($category_slug))
		{
			Helper::setGlobalNotify("Matéria não encontrada", "Escolha uma das matérias abaixo");
			return Redirect::route('materias',null, 301);
		}

		if(!$this->getCourse($category_slug, $course_slug))
		{
			Helper::setGlobalNotify("Curso não encontrado", "Você foi redirecionado para a página da matéria");
			return Redirect::route('categoria', array($category_slug), 301);
		}

		if(!$this->getModulo($category_slug, $course_slug, $module_slug))
		{
			Helper::setGlobalNotify("Módulo não encontrado", "Você foi redirecionado para a página do curso");
			return Redirect::route('curso', array($category_slug, $course_slug), 301);
		}

		$blocked_lesson = false;

		if(Input::has('force_flash'))
		{
			if(Input::get('force_flash') == 'true')
			{
				Cookie::queue('force_flash', true, 60*24*30);
				return Redirect::route('aula', array($category_slug, $course_slug, $module_slug, $lesson_slug));
			}
			else
			{
			    setcookie('force_flash', null, -10, '/');
			    unset($_COOKIE['force_flash']);
			    return Redirect::route('aula', array($category_slug, $course_slug, $module_slug, $lesson_slug));
			}
		}

		$this->view_variables['lesson'] = $this->getLesson($category_slug, $course_slug, $module_slug, $lesson_slug);
		$this->view_variables['console_ajax'] = (Input::has('console_ajax') && Input::get('console_ajax')) ? true : false;

		$this->view_variables['criteo_product_item'] = Helper::setCriteoProductItemFromCategorySlug($this->category->slug);

		if($this->isSpecialLesson()){
			if(Auth::guest()) return $this->fullGuestView();

			$this->setSpecialLessonVars();
			$controller = $this->getControllerFromSpecialLesson();
			return $controller->showByLessonController($this->special_lesson_vars);
		}

		if($this->isExerciseLesson()){
			$this->setSpecialLessonVars();
			$controller = new AulaExerciseController();
			$this->special_lesson_vars->exercise_number = $exercise_number;
			return $controller->showByLessonController($this->special_lesson_vars);
		}

		if(empty($this->view_variables['lesson']->id))
		{
			Helper::setGlobalNotify("Aula não encontrada", "Você foi redirecionado para a página do curso");
			return Redirect::route('curso', array($category_slug, $course_slug), 301);
		}

		if(Auth::guest())
		{
			return $this->fullGuestView();
		}
		$blocked_lesson_math_academy = false;
		$this->view_variables['category_slug'] = $category_slug;
		$this->view_variables['course_slug'] = $course_slug;
		$this->view_variables['module_slug'] = $module_slug;

		$this->view_variables = $this->getModulesNearModules($this->view_variables);

		$order_prev = $this->view_variables['lesson']->order - 1;
		$order_next = $this->view_variables['lesson']->order + 1;

		$module = Module::getModuleFromId($this->view_variables['lesson']->module_id);

		$this->view_variables['points_module'] = $module->lesson_count*100 + $module->exercise_count*50 + 1000;
		$this->view_variables['points_user'] = 0;
		$this->view_variables['points_togain'] = 100;

		if(Auth::check())
		{
			foreach($this->view_variables['other_lessons'] as $l)
			{
				if($l->order == $order_prev) $this->view_variables['lesson_prev'] = $l;
				elseif($l->order == $order_next) $this->view_variables['lesson_next'] = $l;
				$l->watched = false;
			}
		}

		if(Category::isSpecialFree($this->category->id)){
			$blocked_lesson = false;
		}
		elseif( Category::isAcademy( $this->category->id ) ){
			if(!Payments::userHasAcademyValidPayment()){
				foreach($this->view_variables['other_lessons'] as $index=>$other_lesson){
					if($other_lesson->id === $this->view_variables['lesson']->id){
						if($index >= 2){
							return View::make('pages.aula.internal-locked-academia')->with($this->view_variables);
						}
					}
				}
			}
		}
		elseif( Category::isFiocruz( $this->category->id ) ){
			if(!Payments::userHasFiocruzValidPayment()){
				foreach($this->view_variables['other_lessons'] as $index=>$other_lesson){
					if($other_lesson->id === $this->view_variables['lesson']->id){
						if($index >= 2){
							return View::make('pages.aula.internal-locked-fiocruz')->with($this->view_variables);
						}
					}
				}
			}
		}
		elseif( Category::isEssay( $this->category->id ) ){
			if(!Payments::userHasEssayValidPayment()){
				foreach($this->view_variables['other_lessons'] as $index=>$other_lesson){
					if($other_lesson->id === $this->view_variables['lesson']->id){
						if($index >= 2){
							return View::make('pages.aula.internal-locked-essay')->with($this->view_variables);
						}
					}
				}
			}
		}
		elseif( Category::isFiocruz( $this->category->id ) ){
			if(!Payments::userHasFiocruzValidPayment()){
				foreach($this->view_variables['other_lessons'] as $index=>$other_lesson){
					if($other_lesson->id === $this->view_variables['lesson']->id){
						if($index >= 2){
							return View::make('pages.aula.internal-locked-fiocruz')->with($this->view_variables);
						}
					}
				}
			}
		}
		elseif( Category::isIbge( $this->category->id ) ){
			if(!Payments::userHasIbgeValidPayment()){
				foreach($this->view_variables['other_lessons'] as $index=>$other_lesson){
					if($other_lesson->id === $this->view_variables['lesson']->id){
						if($index >= 2){
							return View::make('pages.aula.internal-locked-ibge')->with($this->view_variables);
						}
					}
				}
			}
		}
		elseif( Category::isVestibulares( $this->category->id ) ){
			if(!Payments::userHasVestibularesValidPayment()){
				foreach($this->view_variables['other_lessons'] as $index=>$other_lesson){
					if($other_lesson->id === $this->view_variables['lesson']->id){
						if($index >= 2){
							return View::make('pages.aula.internal-locked-vestibulares')->with($this->view_variables);
						}
					}
				}
			}
		}
		elseif(Payments::userHasPremiumValidPayment()){
			$blocked_lesson = false;
		}
		else{
			PremiumLead::create(['type' => 7, 'product_id' => $this->view_variables['lesson']->course_product_id]);
			foreach($this->view_variables['other_lessons'] as $index=>$other_lesson){
				if($other_lesson->id === $this->view_variables['lesson']->id){
					if($index >= 2){
						return View::make('pages.aula.internal-locked')->with($this->view_variables);
					}
				}
			}
		}

		if(isset($this->view_variables['lesson_prev']))
			MSSession::put('rel_prev', URL::route('aula', array($category_slug, $course_slug, $module_slug, $this->view_variables['lesson_prev']->slug)));

		if(isset($this->view_variables['lesson_next']))
			MSSession::put('rel_next', URL::route('aula', array($category_slug, $course_slug, $module_slug, $this->view_variables['lesson_next']->slug)));

		$this->view_variables['blocked_lesson'] = $blocked_lesson;
		if($this->view_variables['lesson']->video_count)
		{
			return $this->videoLesson();

		}
		elseif($this->view_variables['lesson']->text_count)
		{
			$this->view_variables['text'] = Text::getTextsFromLesson($this->view_variables['lesson']->id)[0];
			$this->view_variables['text']->text = CDN::getImagesFromBucket($this->view_variables['text']->text);

			if($blocked_lesson){
				return Redirect::route('curso', array($category_slug, $course_slug))->with('force_buy', true);
			}
			elseif(!Auth::check()){
				$this->setGuestVariables();
				return View::make('pages.aula.guest', $this->view_variables);
			}
			else{
				return View::make('pages.aula.texto', $this->view_variables);
			}
		}
		else
		{
			$this->view_variables['exercises'] = Exercise::getExercisesFromlesson($this->view_variables['lesson']->id);

			if(Auth::check())
				$exercise_log = MDB::client()->exercise_log->find(['user_id'=>User::getAuthId(), 'lesson_id'=>$this->view_variables['lesson']->id]);
			else
				$exercise_log = array();

			$exercise_status = array();
			foreach($exercise_log as $e)
			{
				if(!isset($exercise_status[$e['exercise_id']])){
					$exercise_status[$e['exercise_id']] = $e['answer_status'];
				}
				elseif($exercise_status[$e['exercise_id']] != 3){
					$exercise_status[$e['exercise_id']] = $e['answer_status'];
				}
			}

			foreach($this->view_variables['exercises'] as $e_count => $e)
			{
				$e->color = 'gray';

				$e->text = str_replace('<img', '<img alt="Imagem Aula - Me Salva"', $e->text);
				$e->text = CDN::getImagesFromBucket($e->text);
				$e->text = preg_replace('/(data-mathml="(.|\n)+?")/', '',$e->text);
				$e->text = preg_replace('/(src="\/plugins\/ckeditor(.|\n)+?\/)/', 'data-src="/plugins/ckeditor/',$e->text);
				$e->text = preg_replace('/alt=""/', 'alt="..."',$e->text);
				$e->text = preg_replace('/(alt="(.|\n)+?")/', 'alt="..."',$e->text);



				$e->resolution = CDN::getImagesFromBucket($e->resolution);
				$e->resolution = preg_replace('/(data-mathml="(.|\n)+?")/', '',$e->resolution);
				$e->resolution = preg_replace('/(src="\/plugins\/ckeditor(.|\n)+?\/)/', 'data-src="/plugins/ckeditor/',$e->resolution);
				$e->resolution = preg_replace('/alt=""/', 'alt="..."',$e->resolution);
				$e->resolution = preg_replace('/(alt="(.|\n)+?")/', 'alt="..."',$e->resolution);


				if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
					$e->text = str_replace('http://', 'https://', $e->text);

				if(isset($exercise_status[$e->id]))
				{
					if($exercise_status[$e->id] == 3) $e->color = 'green';
					elseif($exercise_status[$e->id] == 4) $e->color = 'red';
				}

				$e->alternatives = ExerciseAlternative::getAlternativesFromExercise($e->id);

				foreach($e->alternatives as $a)
				{
					$a->mark = '';
					if(isset($exercise_status[$e->id]) && $exercise_status[$e->id] == 3 && $a->alternative_correct) $a->mark = 'marked-yes';
					$a->alternative_text = CDN::getImagesFromBucket($a->alternative_text);
					$a->alternative_text = preg_replace('/(data-mathml="(.|\n)+?")/', '',$a->alternative_text);
					$a->alternative_text = preg_replace('/(src="\/plugins\/ckeditor(.|\n)+?\/)/', 'data-src="/plugins/ckeditor/',$a->alternative_text);
					$a->alternative_text = preg_replace('/alt=""/', 'alt="..."',$a->alternative_text);
					$a->alternative_text = preg_replace('/(alt="(.|\n)+?")/', 'alt="..."',$a->alternative_text);
				}
			}

			$this->view_variables['exercise_number'] = $exercise_number ? $exercise_number : 1;

			if($blocked_lesson){
				return Redirect::route('curso', array($category_slug, $course_slug))->with('force_buy', true);
			}
			elseif(!Auth::check()){
				$this->setGuestVariables();
				return View::make('pages.aula.guest', $this->view_variables);
			}
			else{
				return View::make('pages.aula.exercicios', $this->view_variables);
			}
		}
	}

	private function fullGuestView(){
		PremiumLead::create(['type' => 7, 'product_id' => $this->view_variables['lesson']->course_product_id]);
		$this->setGuestVariables();
		return View::make('pages.aula.guest', $this->view_variables);
	}

	private function videoLesson(){
		$this->view_variables['video'] = $this->getLessonVideo($this->view_variables['lesson']->id);
		$teachers = Module::getTeachersFromModule($this->view_variables['lesson']->module_id);

		if(empty($teachers)) {
			$this->view_variables['teachers'] = NULL;
		}

		foreach ($teachers as $t) {
			$this->view_variables['teachers'][] = Inthash::hash($t->user_id);
		}

		

		if($this->view_variables['blocked_lesson']){
			if($this->view_variables['blocked_lesson_math_academy']){
				//TODO Trocar isso!
				return View::make('pages.aula.internal-locked')->with($this->view_variables);
			}
			else{
				return View::make('pages.aula.internal-locked')->with($this->view_variables);
			}

		}
		else{
			$fremium = $this->adjustFreemium();
			if(!is_null($fremium)){
				return $fremium;
			}
			if($this->view_variables['lesson']->maintenance){
				return View::make('pages.aula.maintenance', $this->view_variables);
			}

			if( in_array((int) User::getAuthId(), [14,602358,594251, 357186]) ){
				if((int) User::getAuthId() == 14){
					$this->view_variables['html5_test'] = true;
				}
				elseif(((int) User::getAuthId() == 602358) && Agent::isMobile() && (Agent::platform() === 'linux') &&  (Agent::device() === 'Samsung')){
					$this->view_variables['html5_test'] = true;
				}
				elseif(((int) User::getAuthId() == 594251) && (Agent::platform() === 'Android' || Agent::platform() === 'AndroidOS')){
					$this->view_variables['html5_test'] = true;
				}
				elseif(((int) User::getAuthId() == 357186) && (Agent::platform() === 'Android' || Agent::platform() === 'AndroidOS')){
					$this->view_variables['html5_test'] = true;
				}

			}
			return View::make('pages.aula.video', $this->view_variables);
		}
	}

	private function setGuestVariables(){
		$this->view_variables['education_types'] = EducationType::getActivesEducationTypes();
		$this->view_variables['user_objectives'] = UserObjective::getUserObjectives();
	}

	private function isExerciseLesson(){
		return $this->view_variables['lesson']->item_type == 'exercise_list' || $this->view_variables['lesson']->type == 'exercise';
	}

	public function getRedirectAula($category_slug, $course_slug, $lesson_slug)
	{
		return Redirect::to(URL::route('aula',array($category_slug, $course_slug, $lesson_slug)));
	}

	private function getModulo($category_slug, $course_slug, $module_slug)
	{
		$module = Module::where('slug', '=', $module_slug);

		if(!$module->count())
			return false;

		$this->module = $module->first();
		return true;
	}

	private function isSpecialLesson(){
		$special_lessons = ['checkpoint_2016_1', 'randon_marathon', 'random_exercises', 'monitoring'];
		return in_array($this->view_variables['lesson']->item_type, $special_lessons);
	}

	private function getControllerFromSpecialLesson(){
		switch($this->view_variables['lesson']->item_type){
			case 'checkpoint_2016_1': return new Checkpoint2016Controller;
			case 'random_exercises': return new 	RandomExercisesController;
			case 'randon_marathon': return new RandonMarathonController;
			case 'monitoring': return new LessonMonitoringController;
		}
	}

	private function isCheckpoint2016(){
		$special_lessons = ['checkpoint_2016_1'];
		return in_array($this->view_variables['lesson']->item_type, $special_lessons);
	}

	private function isRandomExercises(){
		$special_lessons = ['random_exercises'];
		return in_array($this->view_variables['lesson']->item_type, $special_lessons);
	}

	private function setSpecialLessonVars(){
		$this->special_lesson_vars = new stdClass;
		$this->special_lesson_vars->category = $this->category;
		$this->special_lesson_vars->course = $this->course;
		$this->special_lesson_vars->module = $this->module;
		$this->special_lesson_vars->lesson = $this->view_variables['lesson'];
		$this->special_lesson_vars->criteo_product_item = $this->view_variables['criteo_product_item'];
	}

	private function getPrevious($list, $index)
	{
		if(isset($list[$index-1])){
			if($list[$index-1]->maintenance == 1) {
				return $this->getPrevious($list, $index-1);
			}
			return $list[$index-1];
		}
		return NULL;
	}

	private function getModulesNearModules($view_variables)
	{
		$modules = Module::getModuleFromCourse($view_variables['lesson']->course_id);

		$view_variables['module_prev'] = null;
		$view_variables['module_next'] = null;

		foreach($modules as $k=>$m)
		{
			if($m->id == $view_variables['lesson']->module_id)
			{
				if(isset($modules[$k-1]))
				{
					$view_variables['module_prev'] = $modules[$k-1];
					$view_variables['module_prev']->first_lesson = Lesson::getLessonFullFromModule($view_variables['category_slug'], $view_variables['course_slug'], $view_variables['module_prev']->slug)[0];
				}
				if(isset($modules[$k+1]))
				{
					$view_variables['module_next'] = $modules[$k+1];
					$view_variables['module_next']->first_lesson = Lesson::getLessonFullFromModule($view_variables['category_slug'], $view_variables['course_slug'], $view_variables['module_next']->slug)[0];
				}
				$view_variables['module_next'] = isset($modules[$k+1]) ? $modules[$k+1] : null;
			}
		}

		$view_variables['other_lessons'] = Lesson::getLessonFullFromModule($view_variables['category_slug'], $view_variables['course_slug'], $view_variables['module_slug']);
		return $view_variables;
	}

	private function getLessonVideo($lesson_id)
	{
		$video = Video::getVideosFromLesson($lesson_id)[0];

		$is_mobile = false;
		if(isset($_COOKIE['force_flash']) || Agent::isMobile()) {
			$is_mobile = true;
		}

		Session::put('og_type', 'video');

		$video->include_view = 'pages.aula.videos.';
		if($video->samba_url && $video->samba_active) {
			$video->include_view .= 'samba';
		}
		elseif($video->vimeo_url && Cookie::get('force_vimeo')) {
			$video->include_view .= 'vimeo';
		}
		elseif($video->wistia_url) {
			$video->include_view .= 'wistia';
		}
		elseif($video->vimeo_url) {
			$video->include_view .= 'vimeo';
		}
		elseif($video->youtube_url && $is_mobile) {
			$video->include_view .= 'youtube-mobile';
		}
		elseif($video->youtube_url) {
			$video->include_view .= 'youtube';
			$video->youtube_id = Youtube::getId($video->youtube_url);
		}
		elseif($video->samba_url) {
			$video->include_view .= 'samba';
		}

		return $video;
	}

	private function setLessonExercise($lesson_id)
	{
		$this->lesson_exercise = Exercise::getExercisesFromlesson($lesson_id);
	}

	public function checkProduct()
	{
		$course_lesson = Lesson::getLessonsFromCourse($this->course->id);

		$force_free[] = 0;
		$aux_course_lesson = CourseLesson::where('course_id', '=', $this->course->id);
		if($aux_course_lesson->count())
		{
			$aux_course_lesson = $aux_course_lesson->get();
			foreach($aux_course_lesson as $a)
			{
				if($a->force_free == 1)
					$force_free[] = $a->lesson_id;
			}
		}
		return false;
	}

	private function getCategory($category_slug)
	{
		$category = Category::where('slug', '=', $category_slug);
		if(!$category->count())
			return false;
		$this->category = $category->first();
		return true;
	}

	private function getCourse($category_slug, $course_slug = null)
	{
		if(is_null($course_slug))
		{
			$course_slug = $category_slug;
			$course = Course::where('slug', '=', $course_slug);
			if(!$course->count())
				return false;
			$this->course = $course->first();
		}
		else
		{
			$course = Course::getCourseFromSlug($category_slug, $course_slug);

			if(!count($course))
				return false;
			$this->course = $course[0];
			$this->course->preview = Helper::abbreviateText($this->course->description, 700, false);
			if(substr_count($this->course->preview, '<li>') > substr_count($this->course->preview, '</li>'))
				$this->course->preview = $this->course->preview.'</li></ol>';
			if(substr_count($this->course->preview, '<p>') > substr_count($this->course->preview, '</p>'))
				$this->course->preview = $this->course->preview.'</p>';
		}
		return true;
	}

	public function makeSequence($index)
	{
		$seq_menu = null;
		if(sizeof($index) > 1)
		{
			$j = 0;
			foreach($index as $i)
			{
				$a = explode(']: ', $i);
				if(sizeof($a) > 1)
				{
					switch($seq_menu[$j]['type'] = substr(trim($a[0]), 1, strlen($a[0])))
					{
						case 'Vídeo': case 'Video': case 'video':
							$model = new Video;
							break;
						case 'Text': case 'text': case 'texto': case 'Texto':
							$model = new Text;
							break;
					}
					$id = $model::where('name', '=', $a[1])->first()->id;
					$seq_menu[$j]['id'] = $id;

					$j++;
				}
			}
		}
		else
		{
			$seq_id = 0;
			if($this->lesson_video)
			{
				foreach($this->lesson_video as $video)
				{
					$seq_menu[$seq_id]['type'] = 'video';
					$seq_menu[$seq_id]['id']   = $video->video_id;
					$seq_id++;
				}
			}
			if($this->lesson_text)
			{
				foreach($this->lesson_text as $text)
				{
					$seq_menu[$seq_id]['type'] = 'text';
					$seq_menu[$seq_id]['id']   = $text->text_id;
					$seq_id++;
				}
			}
			if($this->lesson_exercise)
			{
				foreach($this->lesson_exercise as $exercise)
				{
					$seq_menu[$seq_id]['type'] = 'exercise';
					$seq_menu[$seq_id]['id']   = $exercise->id;
					$seq_id++;
				}
			}
		}
		return $seq_menu;
	}

	public function setModels($lesson_seq)
	{
		foreach($lesson_seq as $s)
		{
			switch($s['type'])
			{
				case 'Vídeo': case 'Video': case 'video':
					$models[] = Video::where('id', '=', $s['id'])->first();
				break;
				case 'Text': case 'text': case 'texto': case 'Texto':
					$models[] = Text::where('id', '=', $s['id'])->first();
				break;
				case 'Exercise': case 'exercise':
					$models[] = Exercise::where('id', '=', $s['id'])->first();
				break;
			}
		}
		return $models;
	}

	public function getPreviousLesson()
	{
		return;
		$lesson_id = $this->lesson->id;
		$module_id = $this->module[0]->id;
		$previous_lesson = null;

		$module_lesson = ModuleLesson::where('lesson_id', '=', $lesson_id)->first();
		$previous_lesson_list = ModuleLesson::where('order', '<', $course_lesson->order)->where('course_id', '=', $course_id)->orderBy('order', 'desc');
		if($previous_lesson_list->count())
		{
			$previous_lesson_list = $previous_lesson_list->get();
			foreach($previous_lesson_list as $k=>$prev)
			{
				$previous = Lesson::where('id', '=', $prev->lesson_id)->where('is_session', '=', '0')->where('active', '=', '1')->where('maintenance', '=', 0);
				if($previous->count())
				{
					$previous = $previous->first();
					$previous_lesson = $previous->slug;
					break;
				}
			}
		}

		return $previous_lesson;
	}

	public function getNextLesson()
	{
		return;
		$lesson_id = $this->lesson->id;
		$course_id = $this->course->id;
		$next_lesson = null;

		$course_lesson = CourseLesson::where('lesson_id', '=', $lesson_id)->first();
		$next_lesson_list = CourseLesson::where('order', '>', $course_lesson->order)->where('course_id', '=', $course_id)->orderBy('order', 'asc');
		if($next_lesson_list->count())
		{
			$next_lesson_list = $next_lesson_list->get();
			foreach($next_lesson_list as $k=>$n)
			{
				$next = Lesson::where('id', '=', $n->lesson_id)->where('is_session', '=', '0')->where('active', '=', '1')->where('maintenance', '=', 0);
				if($next->count())
				{
					$next = $next->first();
					$next_lesson = $next->slug;
					break;
				}
			}
		}

		return $next_lesson;
	}

	public function postAulaExercicios($category_slug, $course_slug, $lesson_slug)
	{
		return View::make('pages.aula.aula-exercicios',setParametersExercise($category_slug, $course_slug, $lesson_slug));
	}

	private function setParametersExercise($category_slug, $course_slug, $lesson_slug)
	{
		$parameters['encrypted_category_slug']	= '';
		$parameters['encrypted_course_slug']	= '';
		$parameters['encrypted_lesson_slug']	= '';
		$parameters['lesson'] = null;
		$this->exercises = null;

		$this->category = Category::where('slug', '=', $category_slug)->first();
		$this->course 	= Course::where('slug', '=', $course_slug)->first();
		$this->lesson 	= Lesson::where('slug', '=', $lesson_slug)->first();

		$this->setLessonExercise($this->lesson->id);
		$this->createExerciseArrayFromLesson();

		$parameters['encrypted_category_slug']	= Helper::encrypt($this->category->slug);
		$parameters['encrypted_course_slug']	= Helper::encrypt($this->course->slug);
		$parameters['encrypted_lesson_slug']	= Helper::encrypt($this->lesson->slug);
		$parameters['lesson']	= $this->lesson;
		$parameters['exercises']= $this->exercises;

		return $parameters;
	}

	public function createExerciseArrayFromLesson()
	{
		if(!$this->lesson_exercise)
			return array(null, null);

		foreach($this->lesson_exercise as $e)
		{
			$alternatives = ExerciseAlternative::getAlternativesFromExercise($e->id);

			$exercises[$e->id] = $e;
			$exercises[$e->id]->alternatives = $alternatives;
			$exercises[$e->id]->alternative_correct = 0;

			$e->color = 'gray';

			$logs =
				ExerciseLog::where('user_id', '=', Auth::check() ? Auth::user()->id : 0)
				->where('exercise_id', '=', $e->id)
				->orderBy('log_id', 'desc')
				->get();

			if(count($logs) > 0)
			{
				$e->color = 'red';
				foreach($logs as $l)
				{
					if($l['answer_status'] == 3)
					{
						$exercises[$e->id]->alternative_correct = $l['answer_sended'];
						$e->color = 'green';
						break;
					}
					else
						if($exercises[$e->id]->alternative_correct == 0) $exercises[$e->id]->alternative_correct = $logs[0]['answer_sended'];
				}
			}

			foreach($exercises[$e->id]->alternatives as $a)
			{
				$a->mark = '';
				if($exercises[$e->id]->alternative_correct == $a->alternative_id)
				{
					if($a->alternative_correct)
						$a->mark = 'marked-yes';
					else
						$a->mark = 'marked-not';
				}
			}
            $e->text = CDN::getImagesFromBucket($e->text);
		}
		$this->exercises = $exercises;
		return true;
	}

	public function saveLessonLog($input = null)
	{
		//TODO tratar inputs
		$inputs = Input::all();

		if(!Auth::check()){
			return 'false guest';
		}
		$lesson = Lesson::getLessonFullFromIds($inputs['category_id'], $inputs['course_id'], $inputs['module_id'], $inputs['lesson_id']);

		$lesson_insert['category_id'] 	= (int) $inputs['category_id'];
		$lesson_insert['course_id'] 	= (int) $inputs['course_id'];
		$lesson_insert['module_id'] 	= (int) $inputs['module_id'];
		$lesson_insert['lesson_id'] 	= (int) $inputs['lesson_id'];
		$lesson_insert['product_id'] 	= (int) $inputs['product_id'];
		$lesson_insert['user_id'] 	 	= (int) User::getAuthId();
		$lesson_insert['premium']		= true;
		$lesson_insert['force_free']	= $this->checkForceFree($inputs['module_id'], $inputs['lesson_id']);

		LessonLog::create($lesson_insert);

//		$user_point = new PointsManager($inputs);
//		$user_point->createLessonPoint();

//		if(!empty($user_point->getMessages())){
//			return 'false';
//		}

		$this->sendLessonLogIntercon($inputs, $lesson);

		return 'true';
	}

	private function sendLessonLogIntercon($inputs, $lesson)
	{
		$array_intercom = array(
			'category'	    => $lesson->category_name,
			'course'	    => $lesson->course_name,
			'module'	    => $lesson->module_name,
			'lesson'	    => $lesson->name,
			'premium'	    => $inputs['product_id'] > 0 ? 1 : 0,
			'force_free'    => $lesson->force_free
		);

		Intercom::createEvent(
			'logged-lesson', $array_intercom
		);

		Intercom::createEvent(
			'logged-lesson-web', $array_intercom
		);
	}

	private function checkPremium($course_id)
	{
		return 1;
	}

	private function checkForceFree($module_id, $lesson_id)
	{
		$module_lesson = ModuleLesson::where('module_id', '=', $module_id)->where('lesson_id', '=', $lesson_id);
		if(!$module_lesson->count())
			return 0;

		$module_lesson = $module_lesson->first();
		return $module_lesson->force_free;
	}

	public function postAulaExerciseResolution()
	{
		$exercise_id = Helper::decrypt(Input::get('id'));
		$exercise = Exercise::where('id', '=', $exercise_id)->first();
		echo $exercise->resolution;
		return;
	}

	public function postAulaExerciseCorrection()
	{
		extract(Input::all());
		if(!Auth::check()) return json_encode(5);
		if(isset($type) && $type == 'prova')
		{
			$inputs['category_id'] = Helper::decrypt($category_id);
			$inputs['sub_id'] = Helper::decrypt($sub_id);
			$inputs['test_id'] = Helper::decrypt($test_id);
			$inputs['exercise_id'] = Helper::decrypt($exercise_id);
			$inputs['alternative_id'] = $alternative_id;
			$inputs['alternative_value'] = $alternative_value;

			$status = $this->createExerciseCorrectionMessageExam($inputs);

			$alternative_selected = 0;
			foreach($alternative_value as $k=>$i)
				if($i == 1)
					$alternative_selected = $alternative_id[$k];

			ExamLog::create(array(
				'user_id' 			=> Auth::check() ? Auth::user()->id : 0,
				'exam_category_id'	=> (int) ExamCategory::where('id', '=', $inputs['category_id'])->first()->id,
				'exam_sub_id' 		=> (int) ExamSub::where('id', '=', $inputs['sub_id'])->first()->id,
				'exam_test_id' 		=> (int) ExamTest::where('id', '=', $inputs['test_id'])->first()->id,
				'exercise_id' 		=> (int) $inputs['exercise_id'],
				'answer_sended'		=> (int) $alternative_selected,
				'answer_status'		=> (int) $status
			));

			if($status == 3)
			{
				$eid = (int) $inputs['exercise_id'];
				$cat = (int) ExamCategory::where('id', '=', $inputs['category_id'])->first()->id;
				$sub = (int) ExamSub::where('id', '=', $inputs['sub_id'])->first()->id;
				$tst = (int) ExamTest::where('id', '=', $inputs['test_id'])->first()->id;

//				Points::createOld(array('type' => 'exercise_exam', 'from' => 'exam', 'exercise_id' => $eid, 'exam_category_id' => $cat, 'exam_sub_id' => $sub, 'exam_test_id' => $tst));
			}
		}
		else
		{
			$status = $this->createExerciseCorrectionMessage(Input::all());
			if(isset($debug))
			{
				$encrypted_category_id = str_replace(' ', '+', $encrypted_category_id);
				$encrypted_course_id = str_replace(' ', '+', $encrypted_course_id);
				$encrypted_module_id = str_replace(' ', '+', $encrypted_module_id);
				$encrypted_lesson_id = str_replace(' ', '+', $encrypted_lesson_id);
			}

			if(isset($alternative_text))
				$alternative_selected = $alternative_text;
			else
				foreach($alternative_value as $k=>$i)
					if($i == 1)
						$alternative_selected = $alternative_id[$k];

			$cat = (int) Helper::decrypt($encrypted_category_id);
			$cur = (int) Helper::decrypt($encrypted_course_id);
			$mod = (int) Helper::decrypt($encrypted_module_id);
			$lid = (int) Helper::decrypt($encrypted_lesson_id);
			$eid = (int) Helper::decrypt($encrypted_exercise_id);

			ExerciseLog::create(array(
				'user_id' 			=> (int) Auth::check() ? Auth::user()->id : 0,
				'category_id' 		=> $cat,
				'course_id' 		=> $cur,
				'module_id' 		=> $mod,
				'lesson_id' 		=> $lid,
				'exercise_id' 		=> $eid,
				'answer_sended'		=> (int) $alternative_selected,
				'answer_status'		=> (int) $status
			));

			$array_intercom = array(
				'category'	    => Category::where('id', '=', $cat)->first()->name,
				'course'	    => Course::where('id', '=', $cur)->first()->name,
				'module'	    => Module::where('id', '=', $mod)->first()->name,
				'lesson'	    => Lesson::where('id', '=', $lid)->first()->name,
				'exercise'	    => Exercise::where('id', '=', $eid)->first()->name,
				'premium'	    => true
			);

			$intercom = IntercomBasicAuthClient::factory(array(
				'app_id'	=> 'r5z5jbs5',
				'api_key'	=> '7e5d0149558527ffc715245216db09fd1aa3ccc0'
			));

			$user_id = (int) User::getAuthId();

			$intercom->createEvent(array(
				'event_name'	=> 'lesson-exercise-resolved',
				'created_at'	=> time(),
				'user_id'		=> $user_id,
				'metadata'		=> $array_intercom
			));

			$intercom->createEvent(array(
				'event_name'	=> 'lesson-exercise-resolved_web',
				'created_at'	=> time(),
				'user_id'		=> $user_id,
				'metadata'		=> $array_intercom
			));

			if($status == 3)
			{
				$intercom->createEvent(array(
					'event_name'	=> 'lesson-exercise-ok',
					'created_at'	=> time(),
					'user_id'		=> $user_id,
					'metadata'		=> $array_intercom
				));

				$intercom->createEvent(array(
					'event_name'	=> 'lesson-exercise-ok-web',
					'created_at'	=> time(),
					'user_id'		=> $user_id,
					'metadata'		=> $array_intercom
				));
//				Points::newPoint('lesson', array('category_id'=>$cat, 'course_id'=>$cur, 'module_id'=>$mod, 'lesson_id'=>$lid, 'create_log'=>true));
//				Points::createOld(array('type' => 'exercise_lesson', 'from' => 'lesson', 'exercise_id' => $eid, 'category_id' => $cat, 'course_id' => $cur, 'module_id' => $mod, 'lesson_id' => $lid));
			}
			elseif($status == 4) {
				$intercom->createEvent(array(
					'event_name'	=> 'lesson-exercise-fail',
					'created_at'	=> time(),
					'user_id'		=> $user_id,
					'metadata'		=> $array_intercom
				));

				$intercom->createEvent(array(
					'event_name'	=> 'lesson-exercise-fail-web',
					'created_at'	=> time(),
					'user_id'		=> $user_id,
					'metadata'		=> $array_intercom
				));
			}
		}
		return json_encode($status);
	}

	private function createExerciseCorrectionMessage($inputs)
	{
		extract($inputs);
		$exercise = Exercise::where('id','=', Helper::decrypt($encrypted_exercise_id))->first();


		if(!$exercise->count())
			return 0;// Adicionar script para verificar se as alternativas pertencem ao exercício

		if(!$this->validateAlternativeId($inputs))
			return 1;// Deixar script apenas para verificar se pelo menos uma alternativa foi marcada

		if(false)
			return 2; // Fazer script para ver se mais de uma resposta foi enviada ao mesmo tempo

		if(!$this->correctAlternative($inputs))
			return 4;
		else
			return 3;
	}

	private function createExerciseCorrectionMessageExam($inputs)
	{
		extract($inputs);
		$exercise = Exercise::where('id','=', $exercise_id)->first();


		if(empty($exercise))
			return 0;// Adicionar script para verificar se as alternativas pertencem ao exercício

		if(!$this->validateAlternativeIdExam($inputs))
			return 1;// Deixar script apenas para verificar se pelo menos uma alternativa foi marcada

		if(false)
			return 2; // Fazer script para ver se mais de uma resposta foi enviada ao mesmo tempo

		if(!$this->correctAlternativeExam($inputs))
			return 4;
		else
			return 3;
	}

	private function validateAlternativeId($inputs)
	{
		extract($inputs);

		if(isset($alternative_text) && !($alternative_text === false || $alternative_text === 'false'))
			return true;

		if(!isset($alternative_id) || $alternative_id <= 0)
			return false;
		if(is_string($alternative_id))
			$alternative_id = explode(',',$alternative_id);
		if(is_string($alternative_value))
			$alternative_value = explode(',',$alternative_value);


		foreach($alternative_id as $a)
		{
			$alternatives = ExerciseAlternative::where('id', '=', $a)->first();
			if(!$alternatives || $alternatives->exercise_id != Helper::decrypt($encrypted_exercise_id))
				return false;
		}


		foreach($alternative_value as $a)
		{
			if($a == 1)
				return true;
		}

		return false;
	}

	private function validateAlternativeIdExam($inputs)
	{
		extract($inputs);

		if(!$alternative_id)
			return false;

		if(is_string($alternative_id))
			$alternative_id = explode(',',$alternative_id);
		if(is_string($alternative_value))
			$alternative_value = explode(',',$alternative_value);


		foreach($alternative_id as $a)
		{
			$alternatives = ExerciseAlternative::where('id', '=', $a)->first();
			if(!$alternatives || $alternatives->exercise_id != $exercise_id)
				return false;
		}


		foreach($alternative_value as $a)
		{
			if($a == 1)
				return true;
		}

		return false;
	}

	private function correctAlternative($inputs)
	{
		extract($inputs);

		$count = 0;
		$i = 0;
		$status = false;
		$alternatives = ExerciseAlternative::getAlternativesFromExercise(Helper::decrypt($encrypted_exercise_id));

		if(isset($alternative_text) && !($alternative_text === false || $alternative_text === 'false'))
		{
			foreach($alternatives as $a)
			{
				if($a->alternative_text == $alternative_text)
				{

					$status = true;
				}
			}

			return $status;
		}
		else
		{
			if(is_string($alternative_value))
				$alternative_value = explode(',',$alternative_value);

			foreach($alternatives as $a)
			{
				if($alternative_value[$i++] == 1 && $count++ < 1)
				{
					$this->alternative_selected = $a->alternative_id;
					if($a->alternative_correct == 1)
						$status = true;
				}
			}

			return $status;
		}
	}

	private function correctAlternativeExam($inputs)
	{
		extract($inputs);

		$count = 0;
		$i = 0;
		$status = false;

		$alternatives = ExerciseAlternative::getAlternativesFromExercise($exercise_id);

		if(isset($alternative_text) && !($alternative_text === false || $alternative_text === 'false'))
		{
			foreach($alternatives as $a)
			{
				if($a->alternative_text == $alternative_text)
				{
					$status = true;
				}
			}

			return $status;
		}
		else
		{
			if(is_string($alternative_value))
				$alternative_value = explode(',',$alternative_value);

			foreach($alternatives as $a)
			{
				if($alternative_value[$i++] == 1 && $count++ < 1)
				{
					$this->alternative_selected = $a->alternative_id;
					if($a->alternative_correct == 1)
						$status = true;
				}
			}

			return $status;
		}
	}

    public function forceVimeo()
    {
        Cookie::queue('force_vimeo', true, '525600');
        $url = URL::route('sobre-tab', 'contato');
        $msg = "Seu player para vídeos premium foi alterado com sucesso! Caso você ainda esteja tendo problema com a visualização dos vídeos, <a href='$url'>entre em contato com nosso suporte aqui</a>.";
        Helper::setGlobalNotify(":)", $msg);
        return Redirect::route('dashboard');
    }

	private function getLesson($category_slug, $course_slug, $module_slug, $lesson_slug)
	{
		$lesson =  Lesson::getLessonFullFromSlug($category_slug, $course_slug, $module_slug, $lesson_slug);
		return $lesson;
	}

	private function adjustFreemium()
	{
		if(!User::isFreemium()){
			return null;
		}

		if(User::checkSub()){
			return null;
		}

		$other_lessons = $this->view_variables['other_lessons'];
		foreach($other_lessons as $lesson_index=>$lesson){
			if($lesson->id != $this->view_variables['lesson']->id){
				continue;
			}

			if($lesson_index > 1){
				if(Auth::check()){
					if(!Category::isSpecialFree($this->category->id)){
						return View::make('pages.aula.internal-locked')->with($this->view_variables);
					}
					return null;
				}
				Helper::redirectNow('account-sign-in');
			}
		}
	}
}
