// SmartEcoTrack - Quiz System

class QuizGame {
    constructor() {
        this.currentQuestion = 0;
        this.score = 0;
        this.questions = [];
        this.totalQuestions = 0;
        this.answered = false;
        this.timer = null;
        this.timeLeft = 30;
        
        this.init();
    }
    
    async init() {
        await this.loadQuestions();
        this.setupEventListeners();
        this.displayQuestion();
    }
    
    async loadQuestions() {
        try {
            const response = await fetch('api/get_question.php');
            const data = await response.json();
            
            if (data.success) {
                this.questions = data.questions;
                this.totalQuestions = this.questions.length;
                this.updateProgress();
            } else {
                throw new Error('Failed to load questions');
            }
        } catch (error) {
            console.error('Error loading questions:', error);
            this.showError('Failed to load quiz questions. Please try again.');
        }
    }
    
    setupEventListeners() {
        document.querySelectorAll('.option-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleAnswer(e));
        });
        
        document.getElementById('next-btn')?.addEventListener('click', () => this.nextQuestion());
        document.getElementById('restart-btn')?.addEventListener('click', () => this.restart());
    }
    
    displayQuestion() {
        if (this.currentQuestion >= this.totalQuestions) {
            this.finishQuiz();
            return;
        }
        
        const question = this.questions[this.currentQuestion];
        const questionContainer = document.getElementById('question-container');
        
        if (!questionContainer) return;
        
        // Update question number
        document.getElementById('question-number').textContent = 
            `Question ${this.currentQuestion + 1} of ${this.totalQuestions}`;
        
        // Update question text
        document.getElementById('question-text').textContent = question.question;
        
        // Update options
        const options = ['A', 'B', 'C', 'D'];
        options.forEach(option => {
            const btn = document.getElementById(`option-${option}`);
            if (btn) {
                btn.textContent = question[`option_${option.toLowerCase()}`];
                btn.className = 'option-btn';
                btn.disabled = false;
            }
        });
        
        // Reset answered state
        this.answered = false;
        
        // Hide next button
        const nextBtn = document.getElementById('next-btn');
        if (nextBtn) nextBtn.style.display = 'none';
        
        // Start timer
        this.startTimer();
        
        // Animate question
        questionContainer.classList.add('animate-fadeInUp');
        setTimeout(() => questionContainer.classList.remove('animate-fadeInUp'), 600);
    }
    
    async handleAnswer(e) {
        if (this.answered) return;
        this.answered = true;
        
        // Stop timer
        clearInterval(this.timer);
        
        const selectedBtn = e.target;
        const selectedAnswer = selectedBtn.dataset.option;
        const question = this.questions[this.currentQuestion];
        const correctAnswer = question.correct_answer;
        
        // Check answer via API
        try {
            const response = await fetch('api/check_answer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    question_id: question.id,
                    answer: selectedAnswer
                })
            });
            
            const data = await response.json();
            
            if (data.correct) {
                selectedBtn.classList.add('correct');
                this.score += question.points || 10;
                this.showFeedback(true);
            } else {
                selectedBtn.classList.add('wrong');
                document.getElementById(`option-${correctAnswer}`).classList.add('correct');
                this.showFeedback(false);
            }
            
            // Disable all options
            document.querySelectorAll('.option-btn').forEach(btn => {
                btn.disabled = true;
            });
            
            // Show next button
            const nextBtn = document.getElementById('next-btn');
            if (nextBtn) nextBtn.style.display = 'block';
            
            // Update score
            this.updateScore();
            
        } catch (error) {
            console.error('Error checking answer:', error);
        }
    }
    
    showFeedback(isCorrect) {
        const feedback = document.getElementById('feedback');
        if (!feedback) return;
        
        feedback.textContent = isCorrect ? 'Correct! Well done! 🌿' : 'Incorrect. Learn and try again!';
        feedback.className = `feedback ${isCorrect ? 'correct' : 'incorrect'}`;
        feedback.style.display = 'block';
        
        setTimeout(() => {
            feedback.style.display = 'none';
        }, 3000);
    }
    
    nextQuestion() {
        this.currentQuestion++;
        this.displayQuestion();
        this.updateProgress();
    }
    
    updateScore() {
        const scoreElement = document.getElementById('score');
        if (scoreElement) {
            scoreElement.textContent = `Score: ${this.score}`;
        }
    }
    
    updateProgress() {
        const progress = document.getElementById('progress');
        if (progress) {
            const percent = (this.currentQuestion / this.totalQuestions) * 100;
            progress.style.width = `${percent}%`;
        }
    }
    
    startTimer() {
        this.timeLeft = 30;
        const timerDisplay = document.getElementById('timer');
        
        if (!timerDisplay) return;
        
        this.timer = setInterval(() => {
            this.timeLeft--;
            timerDisplay.textContent = `Time: ${this.timeLeft}s`;
            
            if (this.timeLeft <= 10) {
                timerDisplay.style.color = '#D32F2F';
            }
            
            if (this.timeLeft <= 0) {
                clearInterval(this.timer);
                this.answered = true;
                
                // Auto-select wrong answer
                document.querySelectorAll('.option-btn').forEach(btn => btn.disabled = true);
                document.getElementById('next-btn').style.display = 'block';
                
                this.showFeedback(false);
            }
        }, 1000);
    }
    
    async finishQuiz() {
        // Submit results via API
        try {
            const response = await fetch('api/submit_quiz.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    score: this.score,
                    total_questions: this.totalQuestions
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.displayResults();
            }
        } catch (error) {
            console.error('Error submitting quiz:', error);
        }
    }
    
    displayResults() {
        const quizContainer = document.getElementById('quiz-container');
        if (!quizContainer) return;
        
        const percentage = (this.score / (this.totalQuestions * 10)) * 100;
        let grade, message, icon;
        
        if (percentage >= 90) {
            grade = 'Excellent';
            message = 'You are a disaster preparedness expert!';
            icon = '🌟';
        } else if (percentage >= 70) {
            grade = 'Good';
            message = 'You have good knowledge of disaster preparedness.';
            icon = '👍';
        } else if (percentage >= 50) {
            grade = 'Fair';
            message = 'Keep learning about disaster preparedness.';
            icon = '📚';
        } else {
            grade = 'Needs Improvement';
            message = 'Study more about disaster preparedness to stay safe.';
            icon = '💪';
        }
        
        quizContainer.innerHTML = `
            <div class="quiz-results animate-fadeInUp">
                <div class="result-icon">${icon}</div>
                <h2>Quiz Complete!</h2>
                <div class="result-score">
                    <h3>${this.score} / ${this.totalQuestions * 10}</h3>
                    <p>${percentage.toFixed(1)}%</p>
                </div>
                <div class="result-grade">${grade}</div>
                <p class="result-message">${message}</p>
                <button class="btn btn-success" onclick="location.reload()">
                    <i class="fas fa-redo"></i> Take Again
                </button>
                <button class="btn btn-primary" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-home"></i> Back to Dashboard
                </button>
            </div>
        `;
    }
    
    restart() {
        this.currentQuestion = 0;
        this.score = 0;
        this.updateScore();
        this.updateProgress();
        this.displayQuestion();
    }
    
    showError(message) {
        const container = document.getElementById('quiz-container');
        if (container) {
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </div>
                <button class="btn btn-primary" onclick="location.reload()">
                    Try Again
                </button>
            `;
        }
    }
}

// Initialize quiz when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('quiz-container')) {
        window.quizGame = new QuizGame();
    }
});