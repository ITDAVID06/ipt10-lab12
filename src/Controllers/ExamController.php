<?php 

namespace App\Controllers;

use App\Models\Question;
use App\Models\UserAnswer;
use App\Models\User;
use App\Models\ExamAttempt;
use \PDO;
require 'vendor/autoload.php';
use Fpdf\Fpdf;

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

    public function displayExamAttempts()
    {
        // Initialize the ExamAttempt model
        $examAttemptModel = new UserAnswer();
        
        // Fetch all exam attempts
        $attempts = $examAttemptModel->getAllExamAttempts();
        
        
        // Render the data in the view
        return $this->render('exam-attempts', ['attempts' => $attempts]);
    }

    public function exportToPDF($attempt_id)
    {
        // Initialize Course object
        $obj = new UserAnswer();
        // Fetch attempt data for the specific attempt ID
        $data = $obj->exportData($attempt_id); // Fetch the single attempt data
        var_dump($data);
        
        // Create an instance of FPDF
        $pdf = new FPDF();
        $pdf->AddPage();

        // Set PDF Title
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(190, 10, 'Examinee Attempt Details', 0, 1, 'C');
        $pdf->Ln(10);

        // Add Examinee Information
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(50, 10, 'Examinee Name: ' . $data['examinee_name'], 0, 1);
        $pdf->Cell(50, 10, 'Examinee Email: ' . $data['examinee_email'], 0, 1);
        $pdf->Cell(50, 10, 'Attempt Date: ' . $data['attempt_date'], 0, 1);
        $pdf->Cell(50, 10, 'Exam Items: ' . $data['exam_items'], 0, 1);
        $pdf->Cell(50, 10, 'Exam Score: ' . $data['exam_score'], 0, 1);
        $pdf->Ln(10); // Line break after examinee info

        // Add Answer Information
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(190, 10, 'Answers Submitted', 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(50, 10, 'Answer ID: ' . $data['answer_id'], 0, 1);
        $pdf->Cell(50, 10, 'Attempt ID: ' . $data['attempt_id'], 0, 1);
        $pdf->Cell(50, 10, 'Answers: ' . $data['answers'], 0, 1);
        $pdf->Cell(50, 10, 'Date Answered: ' . $data['date_answered'], 0, 1);
        $pdf->Ln(10); // Line break after answers section

        // Output the PDF as a download
        $pdf->Output('D', 'examinee_attempt_' . $attempt_id . '.pdf');
    }


}