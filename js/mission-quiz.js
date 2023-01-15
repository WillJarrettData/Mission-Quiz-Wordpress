
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
            var i = 0;
            for (letter in currentQuestion.answers) {
                answers.push(
                    `<label>
                            <input type="radio" name="question${questionNumber}" value="${letter}">
                            ${currentQuestion.answers[letter]}
                            <div class="checkmark">&nbsp ✓</div>
                            <div class="wrong">&nbsp X</div>
                            <div class="results-container">
                                <div class="percent"></div>
                            </div>
                        </label>`
                );
                i++;
            }

            output.push(
                `<div class="slide">
                        <div class="question">${currentQuestion.question}</div>
                        <br/>
                        <a target="_blank" href="${currentQuestion.link}">
                        <img decoding="async" class="image jetpack-lazy-image" src="${currentQuestion.image}" data-lazy-src="http://$currentQuestion.image?is-pending-load=1" srcset="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"><noscript><img data-lazy-fallback="1" decoding="async" class="image" src="${currentQuestion.image}" /></noscript>
                        </a>
                        <br />
                        <div class="answers">${answers.join("")}</div>
                        </div>`
            );
        }
    );

    quizContainer.innerHTML = output.join('');
}

// show slide function, called from html script and showNextSlide()
function showSlide(n) {
    // move active-slide class from previous slide to slide n
    slides[currentSlide].classList.remove('active-slide');
    slides[n].classList.add('active-slide');

    nextButton.style.display = 'none';
    submitButton.style.display = 'none';
    submitAnswerButton.style.display = 'inline-block';

    // hide checkmarks, x'es, and percent answered, first time currentSlide=n=0
    if (currentSlide != n) {
        const answersContainer = quizContainer.querySelectorAll('.answers')[currentSlide];
        const resultsContainer = answersContainer.querySelectorAll('.results-container');
        resultsContainer.forEach(x => x.setAttribute("style", "display:none"));

        currentSlide = n;
    }
}

// move between slides using this, called from nextButton click
async function showNextSlide() {
    showSlide(currentSlide + 1);
    questionNumberX++;
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

// After all questions, show results function
async function showResults() {
    let percentageCorrect = numCorrect / Object.keys(myQuestions).length

    var allAnswerLinks = []

    // iterate through incorrect answers to display
    for (var i = 0; i < incorrectAnswers.length; i++) {
        answer = incorrectAnswers[i]
        link = myQuestions[answer].link
        question = myQuestions[answer].question
        allAnswerLinks.push(`<p><a target="_blank" href="${link}">${question}</a></p>`)
    };

    allAnswerLinks = allAnswerLinks.join("");

    if (percentageCorrect == 1) {
        resultsContainer.innerHTML = `<div>You scored <strong>${numCorrect} out of ${myQuestions.length}</strong>.</div>
                    <br />
                    <p>Clearly you should be the one setting the questions – you achieved a perfect score. Your knowledge of Mission current affairs is unparalleled!</p>
                    <button class="button1" onClick="window.location.reload();">Start again?</button>`;
    }
    else if (percentageCorrect > 0.5) {
        resultsContainer.innerHTML = `<div>You scored <strong>${numCorrect} out of ${myQuestions.length}</strong>.</div>
                    <br />
                    <p>Nicely done! You clearly keep up with Mission affairs, although there are still one or two gaps in your knowledge.</p>
                    <p>The questions you answered incorrectly are listed below. Click the links to find the answers and get your score even higher.</p>
                    ${allAnswerLinks}
                    <button class="button1" onClick="window.location.reload();">Start again?</button>`;
    }
    else if (percentageCorrect > 0) {
        resultsContainer.innerHTML = `<div>You scored <strong>${numCorrect} out of ${myQuestions.length}</strong>.</div>
                    <br />
                    <p>Not bad! You still have a way to go until you can claim mastery of Mission current affairs, but you are off to a solid start.</p>
                    <p>The questions you answered incorrectly are listed below. Click the links to find the answers.</p>
                    ${allAnswerLinks}
                    <button class="button1" onClick="window.location.reload();">Start again?</button>`;
    }
    else if (percentageCorrect == 0) {
        resultsContainer.innerHTML = `<div>You scored <strong>${numCorrect} out of ${myQuestions.length}</strong>.</div>
                    <br />
                    <p>Oh dear! If you answered all of the questions in this quiz entirely at random, there would only be a 13% chance of getting every single answer wrong.</p>
                    <p>So, not only does your knowledge of Mission current affairs leave something to be desired, but you may also be pretty unlucky.</p>
                    <p>But not to worry – help is at hand. Click the links below to find the answers to all the questions in this week's quiz.</p>
                    ${allAnswerLinks}
                    <button class="button1" onClick="window.location.reload();">Start again?</button>`;
    }

    quiz.innerHTML = ``;
    document.getElementById('submit').style.display = "none";
    document.getElementById('next').style.display = "none";
}

var questionNumberX = 0;
let numCorrect = 0;
var incorrectAnswers = [];
var PercentAnswered = [];

let currentSlide = 0;
