<?php 

namespace App\Controllers;

use App\Models\Question;
use App\Models\UserAnswer;
use App\Models\User;
use \PDO;

class ExamController extends BaseController
{
    public function loginForm()
    {
        $this->initializeSession();

        return $this->render('login-form');
    }

    public function registrationForm()
    {
        $this->initializeSession();

        return $this->render('registration-form');
    }

    public function register()
    {
        $this->initializeSession();
        $data = $_POST;
        // Save the registration to database
        $user = new User();
        $result = $user->save($data);

        if ($result['row_count'] > 0) {
           
            $_SESSION['user_id'] = $result['last_insert_id']; 
            $_SESSION['complete_name'] = $data['complete_name'];
            $_SESSION['email'] = $data['email'];
            $_SESSION['password'] = $data['password'];
    
           
            return $this->render('login-form', $data);

        }
    }

    public function login(){
        $this->initializeSession();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = $_POST;

        // Create an instance of the User model
        $user = new User();
        
        // Verify user credentials
        if ($user->verifyAccess($data['email'], $data['password'])) {
            // Fetch user data using the method we just created
            $sql = "SELECT id, complete_name, email FROM users WHERE email = :email";
            $statement = $user->getDbConnection()->prepare($sql); // Use getDbConnection() instead of accessing $db directly
            $statement->execute(['email' => $data['email']]);
            $userData = $statement->fetch(PDO::FETCH_ASSOC);
            
            // Store user data in session
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['complete_name'] = $userData['complete_name'];
            $_SESSION['email'] = $userData['email'];

            // Prepare data for the pre-exam Mustache template
            $templateData = [
                'complete_name' => $userData['complete_name'],
                'email' => $userData['email'],
            ];

            // Render the pre-exam page with user data
            return $this->render('pre-exam', $templateData); // Pass user data to Mustache template
        } else {
            // Handle invalid login (optional)
            $_SESSION['error'] = "Invalid email or password.";
            return $this->render('login'); // Show login form again
        }
    }

    // If not a POST request, show the login form
    return $this->render('login'); // Show login form
    }

    public function exam()
    {
        
        $this->initializeSession();
        $item_number = 1;

        // If request is coming from the form, save the inputs to the session
        if (isset($_POST['item_number']) && isset($_POST['answer'])) {
            array_push($_SESSION['answers'], $_POST['answer']);
            $_SESSION['item_number'] = $_POST['item_number'] + 1;
        }

        if (!isset($_SESSION['item_number'])) {
            // Initialize session variables
            $_SESSION['item_number'] = $item_number;
            $_SESSION['answers'] = [false];
        } else {
            $item_number = $_SESSION['item_number'];
        }

        $data = $_POST;
        $questionObj = new Question();
        $question = $questionObj->getQuestion($item_number);

        // if there are no more questions, save the answers
        if (is_null($question) || !$question) {
            $user_id = $_SESSION['user_id'];
            $json_answers = json_encode($_SESSION['answers']);

            error_log('FINISHED EXAM, SAVING ANSWERS');
            error_log('USER ID = ' . $user_id);
            error_log('ANSWERS = ' . $json_answers);

            $userAnswerObj = new UserAnswer();
            $score = $questionObj->computeScore($_SESSION['answers']);
            $items = $questionObj->getTotalQuestions();
            $attempt_Id = $userAnswerObj->saveAttempt($user_id, $items, $score);
            $userAnswerObj->save(
                $user_id,
                $json_answers,
                $attempt_Id
            );
            

            header("Location: /result");
            exit;
        }

        $question['choices'] = json_decode($question['choices']);

        return $this->render('exam', $question);
    }

    public function result()
    {
        $this->initializeSession();
        $data = $_SESSION;
        $questionObj = new Question();
        $data['questions'] = $questionObj->getAllQuestions();
        $answers = $_SESSION['answers'];
        foreach ($data['questions'] as &$question) {
            $question['choices'] = json_decode($question['choices']);
            $question['user_answer'] = $answers[$question['item_number']];
        }
        $data['total_score'] = $questionObj->computeScore($_SESSION['answers']);
        $data['question_items'] = $questionObj->getTotalQuestions();

        session_destroy();

        return $this->render('result', $data);
    }

    public function displayExaminees(){
        

    }
}
