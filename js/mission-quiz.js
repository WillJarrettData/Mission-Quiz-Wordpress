
function sendAnswers(quiz_id, question_no, answer_no) {
    var data = {
        action: 'update_answers',
        quiz_id: quiz_id,
        question_no: question_no,
        answer_no: answer_no
    };
    jQuery.post(MissionQuiz.ajaxurl, data, function (response) { handleUpdateAnswerCallback(response); });
}

function handleUpdateAnswerCallback(response) {
    var update_answer_response = jQuery.parseJSON(response);
    if (update_answer_response.status == 1) {
        //Success, update vote count
        if (update_answer_response.percent_answered) {
            PercentAnswered = [...update_answer_response.percent_answered];
        }
    } else {
        //Failure, notify user
        if (update_answer_response.message != null) {
            alert(update_answer_response.message);
        }
    }

    let total_answers = 0.0;
    let answers = {};
    for (var i = 0; i < PercentAnswered.length; i++) {
        total_answers += Number(PercentAnswered[i].answer_total);
        answers[PercentAnswered[i].answer] = Number(PercentAnswered[i].answer_total);
    }

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

// show results function
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

// show slide function
function showSlide(n) {
    slides[currentSlide].classList.remove('active-slide');
    slides[n].classList.add('active-slide');
    nextButton.style.display = 'none';
    submitButton.style.display = 'none';
    submitAnswerButton.style.display = 'inline-block';

    // hide checkmarks, x'es, and percent answered
    if (currentSlide != n) {
        const answersContainer = quizContainer.querySelectorAll('.answers')[currentSlide];
        const resultsContainer = answersContainer.querySelectorAll('.results-container');
        resultsContainer.forEach(x => x.setAttribute("style", "display:none"));

        currentSlide = n;
    }
}

async function showAnswer() {
    submitAnswerButton.style.display = 'none';
    if (currentSlide === slides.length - 1) {
        submitButton.style.display = 'inline-block';
    }
    else {
        nextButton.style.display = 'inline-block';
    }
}

// move between slides using this
async function showNextSlide() {
    showSlide(currentSlide + 1);
    questionNumberX++;
}

// validate answers
function validateAnswers() {

    // grab questions and answers
    const answerContainer = quizContainer.querySelectorAll('.answers')[questionNumberX];
    const selector = `input[name=question${questionNumberX}]:checked`;
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

        var mark = undefined;
        const resultsContainer = answerContainer.querySelectorAll('.results-container')[i];
        const labelContainer = answerContainer.querySelectorAll('label')[i];
        const percent = resultsContainer.querySelector(`.percent`);
        if (inputs[i].value === myQuestions[questionNumberX].correctAnswer) {
            inputs[i].parentElement.classList.add("correct");

            mark = labelContainer.querySelector(`.checkmark`);
            percent.style.backgroundColor = "LightGreen";
        }
        else {
            if (inputs[i].value === userAnswer) {
                inputs[i].parentElement.classList.add("incorrect");
                mark = labelContainer.querySelector('.wrong');
            }

            percent.style.backgroundColor = "LightCoral";
        }

        if (mark !== undefined) {
            mark.style.display = "inline-block";
        }
    }

    if (userAnswer === undefined) {
        for (var i = 0; i < inputs.length; i++) {
            const labelContainer = answerContainer.querySelectorAll('label')[i];
            if (inputs[i].value != myQuestions[questionNumberX].correctAnswer) {
                inputs[i].parentElement.classList.add("incorrect")
                const wrong = labelContainer.querySelector(`.wrong`);
                wrong.style.display = "inline-block";
            }
        }
    }

    sendAnswers(post_id, questionNumberX, answer_no);

    // count correct answers
    if (myQuestions[questionNumberX].correctAnswer === userAnswer) {
        numCorrect++;
    }
    else {
        incorrectAnswers.push(questionNumberX)
    }
}

var questionNumberX = 0;
let numCorrect = 0;
var incorrectAnswers = [];
var PercentAnswered = [];

let currentSlide = 0;
