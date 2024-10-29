<?php

namespace App\Models;

use App\Models\BaseModel;
use \PDO;

class UserAnswer extends BaseModel
{
    protected $user_id;
    protected $answers;

    public function save($user_id, $answers, $attempt_Id)
    {
        $this->user_id = $user_id;
        $this->answers = $answers;

        var_dump([
            'user_id' => $user_id,
            'answers' => $answers,
            'attempt_id' => $attempt_Id
        ]);

        $sql = "INSERT INTO users_answers
                SET
                    user_id=:user_id,
                    answers=:answers,
                    attempt_id=:attempt_id";        
        $statement = $this->db->prepare($sql);
        $statement->execute([
            'user_id' => $user_id,
            'answers' => $answers,
            'attempt_id' => $attempt_Id
        ]);
    
        return $statement->rowCount();
    }

    public function saveAttempt($user_id, $exam_items, $score)
    {
        $sql = "INSERT INTO exam_attempts
                SET
                    user_id=:user_id,
                    exam_items=:exam_items,
                    score=:score";   
        $statement = $this->db->prepare($sql);
        $statement->execute([
            'user_id' => $user_id,
            'exam_items' => $exam_items,
            'score' => $score
        ]);
        return $this->db->lastInsertId();
    }
       
}   