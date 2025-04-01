
<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License along with
// Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Block simple calculator is defined here.
 *
 * @package     block_simple_calculator
 * @copyright   2020 A K M Safat Shahin <safatshahin@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_simple_calculator extends block_base {

    /**
     * Initializes class member variables.
     */
    public function init() {
        // Needed by Moodle to differentiate between blocks.
        $this->title = 'Цифровые компетенции';
    }

    /**
     * Returns the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        global $DB;
        global $PAGE;
        global $USER;
        $this->content = new stdClass();
        $this->content->text = "";
        $context = $PAGE->context;
        $courseID = intval(get_config('simple_calculator','courseId_setting'));
        $altCourseID = intval(get_config('simple_calculator','altCourseId_setting'));
        $testnames = explode(',',get_config('simple_calculator','names_setting'),10);
        $courseQuizes = $DB->get_records('quiz',['course'=>$courseID]);
        $altCourseQuizes = $DB->get_records('quiz',['course'=>$altCourseID]);
        $quizIds = [];
        $altQuizIds = [];
        $dpoquizids = [];
        $altdpoquizids = [];
        $uniqueString = get_config('simple_calculator','uniqueString_setting');


        //Получаем id тестов курса преподавателей
        foreach($courseQuizes as $quiz){
            foreach($testnames as $name){
                if(str_contains($quiz->name,$name) && str_contains($quiz->name,$uniqueString)){
                    array_push($quizIds,$quiz->id);
                }
            }
        }


        //Получаем id тестов курса сотрудников
        foreach($altCourseQuizes as $quiz){
            foreach($testnames as $name){
                if(str_contains($quiz->name,$name) && str_contains($quiz->name,$uniqueString)){
                    array_push($altQuizIds,$quiz->id);
                }
            }
        }


         //Получаем id дпо тестов курса преподавателей
        foreach($courseQuizes as $quiz){
            foreach($testnames as $name){
                if(str_contains($quiz->name,$name) && str_contains($quiz->name,'*')){
                    array_push($dpoquizids,$quiz->id);
                }
            }
        }


        //Получаем id дпо тестов курса сотрудников
        foreach($altCourseQuizes as $quiz){
            foreach($testnames as $name){
                if(str_contains($quiz->name,$name) && str_contains($quiz->name,'*')){
                    array_push($altdpoquizids,$quiz->id);
                }
            }
        }


        //Получаем попытки прохождения одного из тестов
        $quiz_attempt = $DB->get_record('quiz_attempts', array('quiz' => $quizIds[0]));
        $question_usageid = $quiz_attempt->uniqueid;
        $question_attempt = $DB->get_records('question_attempts',array('questionusageid'=>$question_usageid));
        foreach ($question_attempt as $qa){
            if($qa->slot == 1){
                $question_attempt = $qa->questionid;
            }
        }

        //Получаем список ответов на первый вопрос

        $question_answers_obj = $DB->get_records('question_answers',array('question'=>$question_attempt));
        $question_answers = [];
        foreach ($question_answers_obj as $qa){
            if($qa->feedback == 1){
                array_push($question_answers,$qa->answer);
            }
        }
        
        //ID пользователя в зависимости от контекста

        try {
            // Получаем ID пользователя с автоматической валидацией
            $userid = $this->get_user_id_from_course_context();
        } catch (moodle_exception $e) {
            $this->content->text = $this->render_error($e);
        }


        //Выявляем к какому курсу принадлежит пользователь

        $isAlt = true;
        foreach($quizIds as $testid){
            $attempts = $DB->get_records('quiz_attempts', array('quiz' => $testid));
            foreach($attempts as $attempt){
                if($attempt->userid == $userid){
                    $isAlt = false;
                }
            }
        }
        if($isAlt){
            $quizIds = $altQuizIds;
        }


        //Функция для получения результатов

        function aquire_results($testid,$testname,$userid,$answer=NULL){
            global $DB;
            //Получаем попытки прохождения теста
            $attempts = $DB->get_records('quiz_attempts', array('quiz' => $testid));
            //Получаем максимальный балл  
            $quiz = $DB->get_record('quiz',['id'=>$testid]);
            if($quiz->sumgrades!=0){
                $maxGrade = $quiz->sumgrades;
            }
            else{
                $maxGrade =1; 
            }

            $quizYear = date('Y',$quiz->timecreated);
            $median = [];
            $medianfinal =0;
            $attemptcounter = 0;
            $userAttempts = [];
            $finalgrade = 0;
            if(!is_null($attempts)){
                if(!is_null($answer)){
                    foreach ($attempts as $attempt){
                        $uid = $attempt->uniqueid;
                        $qat = $DB->get_record('question_attempts', array('questionusageid' => $uid, 'slot' => 1));
                        if($qat!=false){
                            if(str_contains(mb_stristr($qat->responsesummary,'}',true),$answer)){
                                array_push($median,$attempt->sumgrades);
                                $attemptcounter +=1; 
                                sort($median);
                                if(count($median)%2==0){
                                    $medianfinal = ($median[floor((count($median)-1)/2)]+$median[ceil((count($median)-1)/2)])/2;
                                }
                                else{
                                    $medianfinal = $median[ceil((count($median)-1)/2)];
                                }   
                            }  
                        }
                    }
                }
                else {
                    //Считаем среднее и получаем попытки пользователя
                    foreach($attempts as $attempt){
                        array_push($median,$attempt->sumgrades);
                        $attemptcounter +=1; 
                        if($attempt->userid == $userid){
                            array_push($userAttempts,$attempt);
                        }
                    }
                    sort($median);
                    if(count($median)%2==0){
                        $medianfinal = ($median[floor((count($median)-1)/2)]+$median[ceil((count($median)-1)/2)])/2;
                    }
                    else{
                        $medianfinal = $median[ceil((count($median)-1)/2)];
                    }     
                }
            }
            //На случай если попыток нету чисто в принципе
            else{
                $finalgrade = 0;
                $medianfinal = 0;
            }
            if(count($userAttempts)!=0){
                $tried = true;
                $finalgrade = end($userAttempts)->sumgrades;
            }
            else{
                $finalgrade = 0;
                $tried = false;
            }
            if(str_contains($quiz->name,'*')){
                $quizYear=substr($quiz->name,strpos($quiz->name,'*')-4,4);
            }
            //Считаем результаты теста в процентах
            $finalgrade = ($finalgrade/$maxGrade)*100;
            $medianfinal = ($medianfinal/$maxGrade)*100;
            $results = (object) 
                ['finalGrade'=>$finalgrade,
                'averageGrade'=>$medianfinal,
                'testName'=>$testname,
                'quizId' =>$testid,
                'year'=>$quizYear,
                //Обьект для передачи результатов предыдущих годов
                'prevYearResults'=>[],
                'tried' =>$tried,
                'timecreated' =>$quiz->timecreated,
                'response' => $answer,
                'attemptcounter'=>$attemptcounter,
                ];
        return $results;
        };
        $quizResults = (object)[
            "resultarray" => [],
            "responsearray" => [],
        ];  
        foreach($quizIds as $quizid){
            $quiz = $DB->get_record('quiz',['id'=>$quizid]);   
            $quizname = $quiz->name;
            foreach($testnames as $name){
                if(str_contains($quizname,$name)){
                    $quizname = $name;
                }
            }
            array_push($quizResults->resultarray, aquire_results($quizid,$quizname,$userid));
            foreach($question_answers as $qa){
                array_push($quizResults->responsearray,aquire_results($quizid,$quizname,$userid,$qa));
            }
        }
        $dpoquizresults = [];
        foreach($dpoquizids as $quizid){
            $quiz = $DB->get_record('quiz',['id'=>$quizid]);   
            $quizname = $quiz->name;
            foreach($testnames as $name){
                if(str_contains($quizname,$name)){
                    $quizname = $name;
                }
            }
            array_push($dpoquizresults, aquire_results($quizid,$quizname,$userid));
        }

        foreach($dpoquizresults as $dpores){
            foreach($quizResults->resultarray as $res){
                if(($dpores->testName == $res->testName) && ($dpores->year==$res->year) && ($dpores->tried)){
                    $res->finalGrade=$dpores->finalGrade;
                    $res->averageGrade=$dpores->averageGrade;
                    $res->quizId=$dpores->quizId;
                }
            }
        }
        
        
        
        $quizResults->resultarray = array_values($quizResults->resultarray);
        

        //Настройка рендерера
        $renderer = $this->page->get_renderer('block_simple_calculator');
        if(count($quizResults->resultarray)!=0){
            $this->content->text .= $renderer->render_calculator($quizResults);
        }
        else{
            $this->content->text .= "Что то пошло не так";
        }
        //Вывод контента
        return $this->content;
    }
    function has_config() {
        return true;
    }

    private function validate_context_and_user($context, $default_userid) {
        // 1. Validate context object structure
        if (!is_object($context)) {
            throw new moodle_exception('invalidcontextobject', 'block_simple_calculator');
        }
        
        // 3. Validate context level
        $valid_context_levels = [CONTEXT_SYSTEM, CONTEXT_COURSE, CONTEXT_MODULE, CONTEXT_BLOCK, CONTEXT_USER];
        if (!in_array($context->contextlevel, $valid_context_levels)) {
            throw new moodle_exception('invalidcontextlevel', 'block_simple_calculator');
        }
        
        // 4. Determine user ID based on context
        $userid = $default_userid;
        if ($context->contextlevel == CONTEXT_USER) {
            $userid = $context->instanceid;
            
            // Additional validation for user context
            if ($userid == $default_userid) {
                throw new moodle_exception('selfcontextonly', 'block_simple_calculator');
            }
        }
        
        // 5. Validate user ID
        return $this->validate_user_id($userid);
    }
    
    /**
     * Validate user ID
     * 
     * @param int $userid User ID to validate
     * @return int Validated user ID
     * @throws moodle_exception
     */
    private function validate_user_id($userid) {
        global $DB;
        
        // 1. Basic type and range check
        if (!is_number($userid) || $userid <= 0) {
            throw new moodle_exception('invaliduserid', 'block_simple_calculator');
        }
        
        // 2. Check if user exists in database
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
            throw new moodle_exception('usernotfound', 'block_simple_calculator');
        }
        
        // 3. Check if user is suspended
        if ($DB->get_field('user', 'suspended', ['id' => $userid])) {
            throw new moodle_exception('usersuspended', 'block_simple_calculator');
        }
        
        return (int)$userid;
    }

    private function render_error($exception) {
        return html_writer::div(
            get_string('erroroccurred', 'block_simple_calculator') . $exception->getMessage(),
            'alert alert-danger'
        );
    }

    protected function get_user_id_from_course_context() {
        global $PAGE, $USER;
        
        $context = $PAGE->context;
        
        // 2. Check context level and get user ID
        switch ($context->contextlevel) {
            case CONTEXT_COURSE:
                // For course context - always return current user
                return $this->validate_user_id($USER->id);
                
            case CONTEXT_USER:
                // For user profile context - return profile owner
                return $this->validate_user_id($context->instanceid);
                
            case CONTEXT_BLOCK:
                // For block context - check parent contexts
                return $this->get_user_id_from_block_context($context);
                
            default:
                throw new moodle_exception('unsupportedcontext', 'block_simple_calculator', '', context_helper::get_level_name($context->contextlevel));
        }
    }
    
    private function get_user_id_from_block_context($context) {
        global $USER;
        
        // Get parent context (course or user)
        $parentcontext = $context->get_parent_context();
        
        switch ($parentcontext->contextlevel) {
            case CONTEXT_COURSE:
                return $this->validate_user_id($USER->id);
                
            case CONTEXT_USER:
                return $this->validate_user_id($parentcontext->instanceid);
                
            default:
                throw new moodle_exception('invalidparentcontext', 'block_simple_calculator');
        }
    }
    

    /**
     * Defines configuration data.
     *
     * The function is called immediatly after init().
     */
    public function specialization() {

        // Load user defined title and make sure it's never empty.
        if (empty($this->config->title)) {
            $this->title = 'Цифровые компетенции';
        } else {
            $this->title = $this->config->title;
        }
    }

    /**
     * Allow multiple instances in a single course?
     *
     * @return bool True if multiple instances are allowed, false otherwise.
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Sets the applicable formats for the block.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats() {
        return array('all' => true);
    }

    /**
     * Tests if this block has been implemented correctly.
     * Also, $errors isn't used right now
     *
     * @return boolean
     */
    public function _self_test() {
        return true;
    }
}
