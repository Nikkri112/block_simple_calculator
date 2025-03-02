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
        $courseID = intval(get_config('simple_calculator','courseId_setting'));
        $testnames = explode(',',get_config('simple_calculator','names_setting'),10);
        $courseQuizes = $DB->get_records('quiz',['course'=>$courseID]);
        $timeCreatedArray = [];
        $yearsArray = [];
        $latestYearQuizIds = [];
        $prevYearQuizIds = [];
        $uniqueString = get_config('simple_calculator','uniqueString_setting');

        //Проходимся по всем тестам курса и собираем возможные года и названия тестов

        foreach($courseQuizes as $quiz){
            foreach($testnames as $name){
                if(str_contains($quiz->name,$name)){
                    array_push($yearsArray,date('Y',$quiz->timecreated));
                    array_push($timeCreatedArray,$quiz->timecreated);
                }
            }
        }

        $yearsArray = array_unique($yearsArray);
        $latest = max($timeCreatedArray);

        //Снова проходимся по всем тестам курса и получаем ID тестов за последний год
        $latestYear = max($yearsArray);

        foreach($courseQuizes as $quiz){
            if(date('Y',$quiz->timecreated)==$latestYear){
                foreach($testnames as $name){
                    if(str_contains($quiz->name,$name) && str_contains($quiz->name,$uniqueString)){
                        array_push($latestYearQuizIds,$quiz->id);
                    }
                }
            }
            else{
                if(date('Y',$quiz->timecreated)!=$latestYear){
                    foreach($testnames as $name){
                        if(str_contains($quiz->name,$name) && str_contains($quiz->name,$uniqueString)){
                            array_push($prevYearQuizIds,$quiz->id);
                        }
                    }
                }
            }
        }
        //Функция для вывода результатов теста

        function aquire_results($testid,$testname,$islastyear){
            global $USER;
            global $DB;
            $results = [];
            $i = 0;

            //Получаем попытки прохождения теста

            try{
                $attempts = $DB->get_records('quiz_attempts', array('quiz' => $testid));
            }catch(Exception $e){
                echo $e->getMessage();
            }

            //Получаем максимальный балл  
            $quiz = $DB->get_record('quiz',['id'=>$testid]);
            if($quiz->sumgrades!=0){
                $maxGrade = $quiz->sumgrades;
            }
            else{
                $maxGrade =1; 
            }
            $quizYear = date('Y',$quiz->timecreated);
            $average = 0;
            $attemptcounter = 0;
            $prevgrade = 0;
            $userAttempts = [];
            $finalgrade = 0;
            if(!is_null($attempts)){
                if (count($attempts)>0) {

                    //Считаем среднее и получаем попытки пользователя

                    foreach($attempts as $attempt){
                        $average += $attempt->sumgrades;
                        $attemptcounter +=1; 
                        if($attempt->userid == $USER->id){
                            array_push($userAttempts,$attempt);
                        }
                    }
                    $average = $average/$attemptcounter;            
                }
            }
            //На случай если попыток нету чисто в принципе
            else{
                $finalgrade = 0;
                $average = 0;
                $prevgrade = 0;
            }
            if(count($userAttempts)!=0){
                $tried = true;
                $finalgrade = end($userAttempts)->sumgrades;
                foreach($userAttempts as $attempt){
                    if(count($userAttempts)>1){    
                    $prevgrade = $userAttempts[count($userAttempts)-2]->sumgrades; 
                    }   
                }
            }
            else{
                $finalgrade = 0;
                $prevgrade = 0;
                $tried = false;
            }

            //Считаем результаты теста в процентах

            $finalgrade = ($finalgrade/$maxGrade)*100;
            $average = ($average/$maxGrade)*100;
            $prevgrade = ($prevgrade/$maxGrade)*100;

            //Если мы рассматриваем результаты последнего года, то выводим среднее и предыдущий результат текущего года
            //А если рассматриваем результаты предыдущих годов то выводим только результат, год и название

            if($islastyear){$results = (object) 
                ['finalGrade'=>$finalgrade,
                'averageGrade'=>$average,
                'prevGrade' =>$prevgrade,
                'testName'=>$testname,
                'quizId' =>$testid,
                //Обьект для передачи результатов предыдущих годов
                'prevYearResults'=>[],
                'tried' =>$tried
                ];}
            else{$results = (object) ['finalGrade'=>$finalgrade,'year'=>$quizYear,'testName'=>$testname,'tried' =>$tried];}
            $i++;
        return $results;
        };
        $latestYearResults = [];
        $prevYearResults = [];

        //Вносим в массив результаты предыдущих годов

         foreach($prevYearQuizIds as $quizid){
            $quiz = $DB->get_record('quiz',['id'=>$quizid]);   
            $quizname =$quiz->name;
            foreach($testnames as $name){
                if(str_contains($quizname,$name)){
                    $quizname = $name;
                }
            }
            array_push($prevYearResults, aquire_results($quizid,$quizname,false));
        }

        //Вносим в массив результаты последнего года
        
        foreach($latestYearQuizIds as $quizid){
            $quiz = $DB->get_record('quiz',['id'=>$quizid]);   
            $quizname = $quiz->name;
            foreach($testnames as $name){
                if(str_contains($quizname,$name)){
                    $quizname = $name;
                }
            }
            array_push($latestYearResults, aquire_results($quizid,$quizname,true));
        }

        //Если названия тестов совпадают, то вносим результат предыдущего года в массив prevYearResults

        foreach($latestYearResults as $res){
            foreach($prevYearResults as $result){
                if($result->testName == $res->testName){
                    $r=(object)['year'=>$result->year,'grade'=>$result->finalGrade,'tried'=>$result->tried];
                    array_push($res->prevYearResults, $r);
                }
            }
        }

        //НУЖНОЕ

        $renderer = $this->page->get_renderer('block_simple_calculator');
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->text .= $renderer->render_calculator($latestYearResults);
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
