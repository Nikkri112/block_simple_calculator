
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
        include(__DIR__."/lib.php");
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
        $questionname = get_config('simple_calculator','question_name_setting');


        $quizIds = extract_needed_quizes($courseQuizes,$testnames,$uniqueString);
        $altQuizIds = extract_needed_quizes($altCourseQuizes,$testnames,$uniqueString);
        $dpoquizids = dpo_extract_needed_quizes($courseQuizes,$testnames);
        $altdpoquizids = dpo_extract_needed_quizes($altCourseQuizes,$testnames);
        
        //ID пользователя в зависимости от контекста
        try {
            // Получаем ID пользователя с автоматической валидацией
            $userid = get_user_id_from_course_context();
        } 
        catch (moodle_exception $e) {
            $this->content->text = render_error($e);
        }

        //Выявляем к какому курсу принадлежит пользователь

        $isAlt = true;
        foreach($quizIds as $testid){
            $attempts = $DB->get_records('quiz_attempts', array('quiz' => $testid->id));
            foreach($attempts as $attempt){
                if($attempt->userid == $userid){
                    $isAlt = false;
                }
            }
        }
        if($isAlt){
            $quizIds = $altQuizIds;
        }


        $question_parent = extract_question_parent($quizIds,$questionname);
        $question_answers = [];
        if($question_parent){
            $question_ids = extract_question_children($question_parent);
            if(!empty($question_ids)){
                foreach($question_ids as $id){
                    array_push($question_answers, extract_question_answers($id));
                }
            }
        }

        $groupedbyyear = sort_by_year_quizes($quizIds);
        $quizres = [];
        $answquizres = [];
        foreach($groupedbyyear as $grp){
            foreach($grp as $id){
                if(is_quiz_with_question($id,$question_parent)){
                    $res = get_users_answers($id->id,$question_answers[0],"part 2");
                }
            }
            foreach($grp as $id){
                array_push($quizres,get_results_all($id,$userid));
                foreach($res as $r){
                    array_push($answquizres,get_results_answers($id,$r));
                }
            }
        }

        //Задаем формат объекта результатов

        $quizResults = (object)[
            "resultarray" => $quizres,
            "responsearray" => $answquizres,
        ];  

        //Проходимся по каждому id и получаем обработанные данные
        //записываем эти данные в массив
        $dpoquizresults = [];
        foreach($dpoquizids as $quizid){
            array_push($dpoquizresults, get_results_all($quizid,$userid));
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
