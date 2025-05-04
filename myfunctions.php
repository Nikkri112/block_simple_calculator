<?php
// Обязательная проверка доступа (Moodle требует это для безопасности)
defined('MOODLE_INTERNAL') || die();

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

    if(!empty($attempts)){
        if(!is_null($answer)){
            foreach ($attempts as $attempt){
                $uid = $attempt->uniqueid;
                $qat = $DB->get_record('question_attempts', array('questionusageid' => $uid, 'slot' => 1));
                if($qat!=false){
                    if(str_contains(mb_stristr($qat->responsesummary,'}',true),$answer)){
                        array_push($median,$attempt->sumgrades);
                        $attemptcounter +=1; 
                    }  
                }
            }
            sort($median);
            if(count($median)%2==0 && count($median)!=0){
                if(floor((count($median)-1)/2)>0){
                    $medianfinal = ($median[floor((count($median)-1)/2)]+$median[ceil((count($median)-1)/2)])/2;
                }
                else{
                    $medianfinal = $median[0];
                }
            }
            else{
                if(count($median)!=0){
                    if(floor((count($median)-1)/2)>0){
                        $medianfinal = $median[ceil((count($median)-1)/2)];
                    }
                    else{
                        $medianfinal = $median[0];
                    }
                }
            }
        }
        else {

            //В случае если не указан вариант ответа собираем общие данные

            foreach($attempts as $attempt){
                array_push($median,$attempt->sumgrades);
                $attemptcounter +=1; 
                if($attempt->userid == $userid){
                    array_push($userAttempts,$attempt);
                }
            }
            sort($median);
            if(count($median)%2==0 && count($median)!=0){
                if(floor((count($median)-1)/2)>0){
                $medianfinal = ($median[floor((count($median)-1)/2)]+$median[ceil((count($median)-1)/2)])/2;
                }
                else{
                    $medianfinal = $median[0];
                }
            }
            else{
                if(count($median)!=0){
                    if(floor((count($median)-1)/2)>0){
                        $medianfinal = $median[ceil((count($median)-1)/2)];
                    }
                    else{
                        $medianfinal = $median[0];
                    }
                }
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

    //Если это тест дпо -- год устанавливаем год в соответствии с названием

    if(str_contains($quiz->name,'*')){
        $quizYear=substr($quiz->name,strpos($quiz->name,'*')-4,4);
    }

    //Считаем результаты теста в процентах

    $finalgrade = ($finalgrade/$maxGrade)*100;
    $medianfinal = ($medianfinal/$maxGrade)*100;

    //Результат в виде объекта

    $results = (object) 
        ['finalGrade'=>$finalgrade,
        'averageGrade'=>$medianfinal,
        'testName'=>$testname,
        'quizId' =>$testid,
        'year'=>$quizYear,
        'tried' =>$tried,
        'timecreated' =>$quiz->timecreated,
        'response' => $answer,
        'attemptcounter'=>$attemptcounter,
        ];
return $results;
}


function extract_needed_quizes($coursequizes, $testnames,$uniqueString)
{
    $newarray = [];
    foreach($coursequizes as $quiz){
        foreach($testnames as $name){
            if(str_contains(strtolower($quiz->name),strtolower($name)) && str_contains(strtolower($quiz->name),$uniqueString)){
                array_push($newarray,(object)[
                    "year"=>date('Y',$quiz->timecreated),
                    "id" =>$quiz->id,
                    "testname"=>$name,
                    "sumgrades"=>$quiz->sumgrades
                ]);
            }
        }
    }
    return $newarray;
}

function dpo_extract_needed_quizes($coursequizes, $testnames){
    $newarray = [];
    foreach($coursequizes as $quiz){
        foreach($testnames as $name){
            if(str_contains($quiz->name,$name) && str_contains($quiz->name,'*')){
                array_push($newarray,(object)[
                    "year"=>substr($quiz->name,strpos($quiz->name,'*')-4,4),
                    "id" =>$quiz->id,
                    "testname"=>$name,
                    "sumgrades"=>$quiz->sumgrades
                ]);
            }
        }
    }
    return $newarray;
}

function extract_question_parent($quizids,$questionname){
    global $DB;
    $questionparent = -1;
    
    foreach($quizids as $quid){
        $attempt = $DB->get_record('quiz_attempts', array('quiz' => $quid->id), "*", IGNORE_MULTIPLE);
        if($attempt!=false){
            $question_usageid = $attempt->uniqueid;
            $question_attempt = $DB->get_records('question_attempts',array('questionusageid'=>$question_usageid));
            if(!empty($question_attempt)){
                foreach ($question_attempt as $qa){
                    $questions = $DB->get_records('question',array('id'=>$qa->questionid));
                    foreach($questions as $question){
                        if($question->name==$questionname && $question->parent == 0)
                        {
                            $questionparent = $question->id;
                        }
                    }
                }
            }
        }
    }
    if($questionparent!=-1){
        return $questionparent;
    }
    else{
        return false;
    }
}


function extract_question_children($questionparent){
    global $DB;
    $resultarray = [];
    $childrenarray = $DB->get_records('question',array('parent'=>$questionparent));
    if(!empty($childrenarray)){
        foreach($childrenarray as $child){
            array_push($resultarray,$child->id);
        }
    }
    return $resultarray;
}

function extract_question_answers($questionid){
    global $DB;
    $result = [];
    $answersarray = $DB->get_records('question_answers',array('question'=>$questionid));
    if(!empty($answersarray)){
        foreach($answersarray as $answer){
            array_push($result,myplugin_strip_tags($answer->answer));
        }
        return $result;
    }
    else{
        return $false;
    }
}

function validate_context_and_user($context, $default_userid) {
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
    return validate_user_id($userid);
}

/**
 * Validate user ID
 * 
 * @param int $userid User ID to validate
 * @return int Validated user ID
 * @throws moodle_exception
 */
function validate_user_id($userid) {
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

function render_error($exception) {
    return html_writer::div(
        get_string('erroroccurred', 'block_simple_calculator') . $exception->getMessage(),
        'alert alert-danger'
    );
}

function get_user_id_from_course_context() {
    global $PAGE, $USER;
    
    $context = $PAGE->context;
    
    // 2. Check context level and get user ID
    switch ($context->contextlevel) {
        case CONTEXT_COURSE:
            // For course context - always return current user
            return validate_user_id($USER->id);
            
        case CONTEXT_USER:
            // For user profile context - return profile owner
            return validate_user_id($context->instanceid);
            
        case CONTEXT_BLOCK:
            // For block context - check parent contexts
            return get_user_id_from_block_context($context);
            
        default:
            throw new moodle_exception('unsupportedcontext', 'block_simple_calculator', '', context_helper::get_level_name($context->contextlevel));
    }
}

function get_user_id_from_block_context($context) {
    global $USER;
    
    // Get parent context (course or user)
    $parentcontext = $context->get_parent_context();
    
    switch ($parentcontext->contextlevel) {
        case CONTEXT_COURSE:
            return validate_user_id($USER->id);
            
        case CONTEXT_USER:
            return validate_user_id($parentcontext->instanceid);
            
        default:
            throw new moodle_exception('invalidparentcontext', 'block_simple_calculator');
    }
}

function myplugin_strip_tags($html) {
    return strip_tags($html);
}

function sort_by_year_quizes($objects){
    
    $grouped = [];
    foreach ($objects as $obj) {
        $grouped[$obj->year][] = $obj;
    }
    return $grouped;
}

function is_quiz_with_question($quizid,$question_parent){
    global $DB;
    $qattempt = $DB->get_record('quiz_attempts', array('quiz' => $quizid->id), "*", IGNORE_MULTIPLE);
    if($DB->record_exists('question_attempts', ['questionusageid' => $qattempt->uniqueid, 'questionid' => $question_parent])){
        return true;
    }
    else{
        return false;
    }
}

function get_users_answers($quizid,$answersarray,string $dokakoystroki){
    global $DB;
    $res = [];
    foreach($answersarray as $answ){
        $res[$answ] = (object)[
            "answer" => $answ,
            "userids" => []
        ];
        $attempts = $DB->get_records('quiz_attempts', array('quiz' => $quizid));
        if(!empty($attempts)){
            foreach ($attempts as $attempt){
                $uid = $attempt->uniqueid;
                $qat = $DB->get_record('question_attempts', array('questionusageid' => $uid, 'slot' => 1));
                if($qat!=false){
                    if(str_contains(mb_stristr($qat->responsesummary,$dokakoystroki,true),$answ)){
                        array_push($res[$answ]->userids,$attempt->userid);
                    }  
                }
            }
        }
    }
    return $res;
}

function median($numbers){
    if(!empty($numbers)){
        sort($numbers);
        $count = count($numbers);
        $middle = floor($count / 2);
        
        return ($count % 2) 
            ? $numbers[$middle] 
            : ($numbers[$middle - 1] + $numbers[$middle]) / 2;
    }
    else{
        return 0;
    }
}

function get_results_all($quizid,$userid){
    global $DB;
    if($quizid->sumgrades!=0){
        $maxGrade = $quizid->sumgrades;
    }
    else{
        $maxGrade =1; 
    }
    $attempts = $DB->get_records('quiz_attempts', array('quiz' => $quizid->id));
    $median = [];
    $medianfinal =0;
    $attemptcounter = 0;
    $userres = 0;
    $tried = false;
    if(!empty($attempts)){
        foreach ($attempts as $attempt){
            $attemptcounter = $attemptcounter+1;
            array_push($median,$attempt->sumgrades);
            if($attempt->userid==$userid){
                $userres = $attempt->sumgrades;
                $tried = true;
            }
        }
    }

    $medianfinal = median($median);
    $userres = ($userres/$maxGrade)*100;
    $medianfinal = ($medianfinal/$maxGrade)*100;

    $results = (object) 
        ['finalGrade'=>$userres,
        'averageGrade'=>$medianfinal,
        'testName'=>$quizid->testname,
        'quizId' =>$quizid->id,
        'year'=>$quizid->year,
        'tried' =>$tried,
        'response' => NULL,
        'attemptcounter'=>$attemptcounter,
        ];
    return $results;
}

function get_results_answers($quizid,$useranswer){
    global $DB;
    if($quizid->sumgrades!=0){
        $maxGrade = $quizid->sumgrades;
    }
    else{
        $maxGrade =1; 
    }
    $attempts = $DB->get_records('quiz_attempts', array('quiz' => $quizid->id));
    $median = [];
    $medianfinal =0;
    $attemptcounter = 0;
    $userres = 0;
    $tried = false;
    if(!empty($attempts)){
        foreach ($attempts as $attempt){
            if(in_array($attempt->userid,$useranswer->userids)){
                array_push($median,$attempt->sumgrades);
                $attemptcounter=$attemptcounter+1;
            }
        }
    }

    $medianfinal = median($median);
    $userres = ($userres/$maxGrade)*100;
    $medianfinal = ($medianfinal/$maxGrade)*100;

    $results = (object) 
        ['finalGrade'=>$userres,
        'averageGrade'=>$medianfinal,
        'testName'=>$quizid->testname,
        'quizId' =>$quizid->id,
        'year'=>$quizid->year,
        'tried' =>$tried,
        'response' => $useranswer->answer,
        'attemptcounter'=>$attemptcounter,
        ];
    return $results;
}