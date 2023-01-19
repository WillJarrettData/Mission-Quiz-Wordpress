
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
        answers[Number(PercentAnswered[i].answer)] = num_answers;
    }

    // Fill in the .percent elements. Set their width and the 'nn%' text
    const slide = quizContainer.querySelectorAll('.slide')[questionIterate];
    const inputs = slide.querySelectorAll('input')
    const percent_labels = slide.querySelectorAll(`.percent-label`);
    const percent_bars = slide.querySelectorAll(`.percent-bar`);
    let len = inputs.length;
    let widths = {};
    let max_percent = 0;
    for (var i = 0; i < len; i++) {
        let percent = 0;
        if (i in answers) {
            percent = Math.round(100 * answers[i] / total_answers);
        }
        widths[i] = percent;
        if (max_percent < percent) {
            max_percent = percent;
        }
        percent_labels[i].innerHTML = `${percent}%`;
    }

    let j = 0;
    // animate percent bar every 10 msec until longest one is full
    let interval_id = setInterval(growWidth, 10);
    function growWidth() {
        for (var i = 0; i < len; i++) {
            let percent_bar = percent_bars[i];
            if (j <= widths[i]) {
                percent_bar.style.width = `${j}%`;
            }
        }
        j++;
        if (j > max_percent) {
            clearInterval(interval_id);
            for (var i = 0; i < len; i++) {
                percent_labels[i].style.display = "inherit";
            }
        }
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

////
//// FUNCTION: ANSWER VALIDATION
////
function validateAnswers(userAnswer, correctAnswer, userAnswerButton, correctAnswerButton) {
    // define variables
    explanationText = "<p>" + myQuestions[questionIterate].explanation + "</p>";
    explanation = document.getElementById('explanation');
    let question_container = userAnswerButton.parentElement.parentElement;
    answers = question_container.getElementsByClassName('button-answers');
    slides = question_container.getElementsByClassName('slide');
    percent_bars = question_container.getElementsByClassName('percent-bar');
    percent_labels = question_container.getElementsByClassName('percent-label');
    labels = question_container.getElementsByClassName('answer-label');

    // disable all the slides
    for (let i = 0; i < slides.length; i++) {
        slides[i].classList.add("disabled");
    }

    // disable all the buttons
    var user_answer_no;
    for (let i = 0; i < answers.length; i++) {
        answer = answers[i];
        if (answer === userAnswerButton) {
            user_answer_no = i;
        }

        answer.classList.add("disabled");
        answer.classList.remove("button-answers-hover");
        answer.disabled = true;

        // Set the background and text colors
        if (answer === correctAnswerButton) {
            labels[i].style.color = "White";
            answer.style.backgroundColor = "LightGreen";
            percent_bars[i].style.backgroundColor = "Green";
        }
        else if (answer === userAnswerButton) {
            answer.style.backgroundColor = "LightCoral";
            percent_bars[i].style.backgroundColor = "Red";
        }
        else {
            answer.style.backgroundColor = "Silver";
            percent_bars[i].style.backgroundColor = "#8e8e8e";
        }
    }

    // Update the database with the latest answer
    // In the callback handleUpdateAnswerCallback() set the percent width and text
    sendAnswers(post_id, questionIterate, user_answer_no);

    correctAnswerButton.classList.add("correct");

    // if correct
    if (userAnswer === correctAnswer) {
        explanation.innerHTML = "<h3 class='correct-word'>CORRECT ✓</h3>"

        numCorrect++;
    }
    // if wrong
    else {
        userAnswerButton.classList.add("incorrect");
        explanation.innerHTML = "<h3 class='incorrect-word'>INCORRECT X</h3>"
    }
    explanation.innerHTML += explanationText

}

let numCorrect = 0;
var incorrectAnswers = [];
