
// Called to update the database and return current totals
function sendAnswers(quiz_id, question_no, answer_no) {
    // Pass the data to PHP code (ajax_update_answers())
    var data = {
        action: 'update_answers',
        quiz_id: quiz_id,
        question_no: question_no,
        answer_no: answer_no
    };
    jQuery.post(MissionQuiz.ajaxurl, data, function (response) { handleUpdateAnswerCallback(response); });
}

// Results returned from PHP code ajax_update_answers() is in response
function handleUpdateAnswerCallback(response) {
    var update_answer_response = jQuery.parseJSON(response);
    if (update_answer_response.status == 1) {
        // Success, save the per-answer vote totals
        // In the form: [{0: total0}, {1: total1}, ... {n, totaln}]
        // If a question has not been answered yet it won't be in the database yet
        if (update_answer_response.percent_answered) {
            PercentAnswered = [...update_answer_response.percent_answered];
        }
    } else {
        //Failure, notify user
        if (update_answer_response.message != null) {
            alert(update_answer_response.message);
            return;
        }
    }

    // Do some math, first determine how many total answers have been made
    let total_answers = 0.0;
    // Create an object that maps answer_index to answer_total
    let answers = {};
    for (var i = 0; i < PercentAnswered.length; i++) {
        num_answers = Number(PercentAnswered[i].answer_total);
        total_answers += num_answers;
        answers[PercentAnswered[i].answer] = num_answers;
    }

    // Fill in the .percent elements. Set their width and the 'nn%' text
    const answerContainer = quizContainer.querySelectorAll('.answers')[questionNumberX];
    const inputs = answerContainer.querySelectorAll('input')
    for (var i = 0; i < inputs.length; i++) {
        const resultsContainer = answerContainer.querySelectorAll('.results-container')[i];
        const percent_element = resultsContainer.querySelector(`.percent`);
        let width = 0;
        if (i in answers) {
            width = 100 * answers[i] / total_answers;
        }
        percent_element.style.width = `${width}%`;
        percent_element.innerHTML = ` ${Math.round(width)}%<br/><br/>`;
    }
}

// build quiz function
function buildQuiz() {
    const output = [];
    myQuestions.forEach(
        (currentQuestion, questionNumber) => {
            const answers = [];
            for (letter in currentQuestion.answers) {
                // create the answer buttons
                answers.push(
                    `<div class="answer-container">
                            <input class="button-answers button-answers-hover" type="button" name="${questionNumber}" id="${currentQuestion.answers[letter]}" >
                        <div class="percent-bar"></div>
                        <label for="${currentQuestion.answers[letter]}" class="answer-label">${currentQuestion.answers[letter]}</label>
                        <div class="percent-label">21%</div>
                    </div>`);
            }

            // create the slide
            output.push(
                `<div class="slide">
                    <img decoding="async" class="image jetpack-lazy-image" src="${currentQuestion.image}" data-lazy-src="http://$currentQuestion.image?is-pending-load=1" srcset="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"><noscript><img data-lazy-fallback="1" decoding="async" class="image" src="${currentQuestion.image}" /></noscript>
                    <div class="question"><p><strong>${currentQuestion.number}. ${currentQuestion.question}</strong></p></div>
                    ${answers.join("")}
                </div>`);
            }
        );

    quizContainer.innerHTML = output.join('');
}
////
//// RESULTS
////
function showResults(resultsContainer){
    // calculate correctness
    let percentageCorrect = numCorrect / Object.keys(myQuestions).length
    if (percentageCorrect == 1) {
        resultsContainer.innerHTML = `<h3>You scored <strong>${numCorrect} out of ${myQuestions.length}</strong>.</h3>
        <p>Clearly you should be setting the questions – your understanding of the Mission is unsurpassed. Congratulations!</p>
        <button class="button1" onClick="window.location.reload();">Start again?</button>`;
    }
    else if (percentageCorrect > 0.5) {
        resultsContainer.innerHTML = `<h3>You scored <strong>${numCorrect} out of ${myQuestions.length}</strong>.</h3>
        <p>Nicely done! Your knowledge of the Mission is impressive. But there's still room to improve!</p>
        <button class="button1" onClick="window.location.reload();">Start again?</button>`;
    }
    else if (percentageCorrect > 0) {
        resultsContainer.innerHTML = `<h3>You scored <strong>${numCorrect} out of ${myQuestions.length}</strong>.</h3>
        <p>Not bad! You still have a way to go until you can claim total understanding of the Mission, but you are off to a solid start.</p>
        <button class="button1" onClick="window.location.reload();">Start again?</button>`;
    }
    else if (percentageCorrect == 0) {
        resultsContainer.innerHTML = `<h3>You scored <strong>${numCorrect} out of ${myQuestions.length}</strong>.</h3>
        <p>Oh dear! Perhaps it's time to give to have another browse through our articles.</p>
        <button class="button1" onClick="window.location.reload();">Start again?</button>`;
    }
    quiz.innerHTML = ``;
};
            ////
            //// SLIDES
            ////
            // move to next slide
            function plusSlides(n) {
                showSlides(slideIndex += n);
                nextButton = document.getElementById('next');
                nextButton.classList.add("hide");
                for (let i = 0; i < answers.length; i++) {
                        answers[i].classList.remove("disabled");
                        answers[i].classList.add("button-answers-hover");
                        answers[i].disabled = false;
                        explanation.innerHTML = ""
                }
                // disable all the slides
                for (let i = 0; i < slides.length; i++) {
                    slides[i].classList.remove("disabled");
                }
                // jump to the top of the next question
                document.getElementById("jump-to-next").scrollIntoView({behavior: 'auto'});
            }
            function showSlides(n) {
                let i;
                let slides = document.getElementsByClassName("slide");
                // remove 'active slide' from previous slide
                if (n > 0) {slides[n-1].classList.remove('active-slide')};
                // if at the end of the quiz
                if (n == slides.length) {
                    nextButton.classList.add("hide");
                    explanation.innerHTML = ""
                    const resultsContainer = document.getElementById('results');
                    showResults(resultsContainer);
                }
                // move to next slide
                if (n != slides.length) {
                    for (i = 0; i < slides.length; i++) {
                        slides[n].classList.add('active-slide');
                    }
                    questionIterate++;
                }
            }

// Turn off the submit answer, turn on either next button, or end quiz,
// called from submitAnswerButton click
async function showAnswer() {
    submitAnswerButton.style.display = 'none';
    if (currentSlide === slides.length - 1) {
        submitButton.style.display = 'inline-block';
    }
    else {
        nextButton.style.display = 'inline-block';
    }
}

            ////
            //// FUNCTION: ANSWER VALIDATION
            ////
            function validateAnswers(userAnswer, correctAnswer, userAnswerButton, correctAnswerButton) {
                // define variables
                answers = document.getElementsByClassName('button-answers')
                explanationText = "<p>" + myQuestions[questionIterate].explanation + "</p>"
                explanation = document.getElementById('explanation')
                slides = document.getElementsByClassName('slide')
                // disable all the slides
                for (let i = 0; i < slides.length; i++) {
                    slides[i].classList.add("disabled");
                }
                // disable all the buttons
                for (let i = 0; i < answers.length; i++) {
                        answers[i].classList.add("disabled");
                        answers[i].classList.remove("button-answers-hover");
                        answers[i].disabled = true;
                }

                // if correct
                if (userAnswer === correctAnswer) {
                    correctAnswerButton.classList.add("correct");
                    explanation.innerHTML = "<h3 class='correct-word'>CORRECT ✓</h3>"

                    numCorrect++;
                }
                // if wrong
                else {
                    correctAnswerButton.classList.add("correct");
                    userAnswerButton.classList.add("incorrect");
                    explanation.innerHTML = "<h3 class='incorrect-word'>INCORRECT X</h3>"
                }
                explanation.innerHTML += explanationText

            };
/*
// validate answers, called from submitAnswerButton click
function validateAnswers() {

    // grab questions and answers
    const answerContainer = quizContainer.querySelectorAll('.answers')[questionNumberX];
    const selector = `input[name=question${questionNumberX}]:checked`;

    // userAnswer is undefined if no input selected
    const userAnswer = (answerContainer.querySelector(selector) || {}).value;
    const inputs = answerContainer.querySelectorAll('input')

    // iterate through answers to validate
    var answer_no = -1;
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].disabled = true;

        if (userAnswer !== undefined) {
            if (inputs[i].value === userAnswer) {
                answer_no = i;
            }
        }

        // set mark to element .checkmark, or element .wrong
        const resultsContainer = answerContainer.querySelectorAll('.results-container')[i];
        const labelContainer = answerContainer.querySelectorAll('label')[i];
        const percent = resultsContainer.querySelector(`.percent`);
        if (inputs[i].value === myQuestions[questionNumberX].correctAnswer) {
            // Make the text Green
            inputs[i].parentElement.classList.add("correct");

            let checkmark = labelContainer.querySelector(`.checkmark`);
            checkmark.style.display = "inline-block";

            percent.style.backgroundColor = "LightGreen";
        }
        else {
            if (inputs[i].value === userAnswer) {
                // Make the text Red
                inputs[i].parentElement.classList.add("incorrect");

                let wrongmark = labelContainer.querySelector('.wrong');
                wrongmark.style.display = "inline-block";
            }

            percent.style.backgroundColor = "LightCoral";
        }
    }

    if (userAnswer === undefined) {
        // No answer selected, set the X on all the wrong entries
        for (var i = 0; i < inputs.length; i++) {
            const labelContainer = answerContainer.querySelectorAll('label')[i];
            if (inputs[i].value != myQuestions[questionNumberX].correctAnswer) {
                // Make the text Red
                inputs[i].parentElement.classList.add("incorrect")

                const wrongmark = labelContainer.querySelector(`.wrong`);
                wrongmark.style.display = "inline-block";
            }
        }
    }

    // Update the database with the latest answer
    // In the callback handleUpdateAnswerCallback() set the percent width and text
    sendAnswers(post_id, questionNumberX, answer_no);

    // count correct answers
    if (myQuestions[questionNumberX].correctAnswer === userAnswer) {
        numCorrect++;
    }
    else {
        incorrectAnswers.push(questionNumberX)
    }
}
*/
var questionNumberX = 0;
let numCorrect = 0;
var incorrectAnswers = [];
var PercentAnswered = [];

let currentSlide = 0;
