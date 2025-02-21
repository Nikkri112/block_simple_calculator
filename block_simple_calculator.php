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
        //require(__DIR__ . '/config.php');
        if ($this->content !== null) {
            return $this->content;
        }
        global $DB;
        global $PAGE;
        global $USER;
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

        foreach($courseQuizes as $quiz){
            foreach($testnames as $name){
                if(str_contains($quiz->name,$name) && str_contains($quiz->name,$uniqueString)){
                    array_push($quizIds,$quiz->id);
                }
            }
        }

        foreach($altCourseQuizes as $quiz){
            foreach($testnames as $name){
                if(str_contains($quiz->name,$name) && str_contains($quiz->name,$uniqueString)){
                    array_push($altQuizIds,$quiz->id);
                }
            }
        }

        foreach($courseQuizes as $quiz){
            foreach($testnames as $name){
                if(str_contains($quiz->name,$name) && str_contains($quiz->name,'*')){
                    array_push($dpoquizids,$quiz->id);
                }
            }
        }

        foreach($altCourseQuizes as $quiz){
            foreach($testnames as $name){
                if(str_contains($quiz->name,$name) && str_contains($quiz->name,'*')){
                    array_push($altdpoquizids,$quiz->id);
                }
            }
        }
        $quiz_attempt = $DB->get_record('quiz_attempts', ['quiz' => $quizIds[0]]);
        $question_usageid = $quiz_attempt->uniqueid;
        $question_attempt = $DB->get_record('question_attempts',['questionusageid'=>$question_usageid,'slot'=>1]);
        $question_answers_obj = $DB->get_records('question_answers',['question'=>$question_attempt->questionid,'feedback'=>1]);
        $question_answers = [];
        foreach ($question_answers_obj as $qa){
            array_push($question_answers,$qa->answer);
        }
        if ($context->contextlevel == CONTEXT_USER) {
            $userid = $context->instanceid; // ID пользователя, чей профиль просматривается
        } else {
            // Не в контексте профиля пользователя
            $userid = $USER->id;
        }
        //Проверяем если есть попытка в педагогическом курсе
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
                        $qat = $DB->get_record('question_attempts', ['questionusageid' => $uid, 'slot' => 1]);
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

        $this->content = new stdClass();
        $this->content->text = '';
        $badIndexes = [];
        for($i=0;$i<count($quizResults->resultarray);$i++){
            for($j=$i;$j<count($quizResults->resultarray);$j++){
                if($quizResults->resultarray[$i]->quizId!=$quizResults->resultarray[$j]->quizId){
                    if(($quizResults->resultarray[$i]->testName==$quizResults->resultarray[$j]->testName) && ($quizResults->resultarray[$i]->year==$quizResults[$j]->year)){
                        if($quizResults->resultarray[$i]->timecreated>=$quizResults->resultarray[$j]->timecreated){
                            array_push($badIndexes,$j);
                        }
                        else{
                            array_push($badIndexes,$i);
                        }
                    }
                }
            }
        }
        foreach($badIndexes as $index){
            unset($quizResults->resultarray[$index]);
        } 
        $quizResults->resultarray = array_values($quizResults->resultarray);
        //НУЖНОЕ
        $renderer = $this->page->get_renderer('block_simple_calculator');
        if(count($quizResults->resultarray)!=0){
            $this->content->text .= $renderer->render_calculator($quizResults);
        }
        else{
            $this->content->text .= "Что то пошло не так";
        }
        return $this->content;
    }
    function has_config() {
        return true;
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
