<?php
/*
 * Plugin Name: MissionQuiz
 * Plugin URI:
 * Description: Demonstration of quiz percentages, very specific to post #9
 * Version: 1.0
 * Author: Craig mautner
 * Author URI:
 * License: GPL2
 *
 *	Copyright 2023 Craig Mautner (mailto:craig.mautner@gmail.com)
 *
 *		This program is free software; you can redistribute it and/or modify
 *		it under the terms of the GNU General Public License, version 2, as
 *		published by the Free Software Foundation.
 *
 *		This program is distributed in the hope that it will be useful,
 *		but WITHOUT ANY WARRANTY; without even the implied warranty of
 *		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
 *		GNU General Public License for more details.
 *
 *		You should have received a copy of the GNU General Public License
 *		along with this program; if not, write to the Free Software
 *		Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA	02110-1301	USA
 *
 *		This Wordpress plugin is released under a GNU General Public License, version 2.
 *		A complete version of this license can be found here:
 *		http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!class_exists("MissionQuiz"))
{
	global $mission_quiz_db_version;
	$mission_quiz_db_version = "1.0";

	class MissionQuiz
	{
		function __construct()
		{
			add_action( 'init', array( &$this, 'init_plugin' ));
			add_action( 'wp_ajax_update_answers', array( &$this, 'ajax_update_answers' ));
			add_action( 'wp_ajax_nopriv_update_answers', array( &$this, 'ajax_update_answers' ));
			add_action( 'wp_head', array( &$this, 'add_ajax_url' ));
        }

		public function setup_plugin() {
			global $wpdb;
			global $mission_quiz_db_version;
			require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );

			if (get_option("mission_quiz_db_version")) {
                if (get_option("mission_quiz_db_version") != $mission_quiz_db_version) {
					die ("downgrade not supported!!!"); // there is no prev version using this var
                }
                return;
			}

			if ( !empty($wpdb->charset) )
				$charset_collate = "DEFAULT CHARACTER SET ".$wpdb->charset;

			$sql[] = "CREATE TABLE ".$wpdb->base_prefix."mission_quiz_answer_totals (
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
			 			quiz_id varchar(32) NOT NULL,
                        question_id bigint(20) NOT NULL,
                        answer varchar(32) NOT NULL,
			 			answer_total bigint(20) NOT NULL DEFAULT 1,
			            KEY answer_id (quiz_id, question_id, answer),
			            CONSTRAINT UNIQUE (quiz_id, question_id, answer)
			            ) ".$charset_collate.";";

			$result = dbDelta ($sql);
			update_option ("mission_quiz_db_version", $mission_quiz_db_version);
		}

		public function init_plugin() {
			$this->load_quiz_client_resources();
		}

		public function load_quiz_client_resources()
		{
			if ( !is_admin() )
			{
				wp_register_style( 'mission-quiz', plugins_url( '/style/mission-quiz.css', __FILE__));
				wp_enqueue_style( 'mission-quiz' );

                // wp_register_script( 'mission-quiz',
				// 		 plugins_url( '/js/mission-quiz.js', __FILE__),
				// 		 array( 'jquery' ),
                //     //     '1.0'
                //     );
				//  wp_enqueue_script('mission-quiz');
			}
		}

		public function add_ajax_url()
		{
			echo '<script type="text/javascript">var MissionQuiz = { ajaxurl: "'.admin_url ('admin-ajax.php').'" };</script>';
		}

		//********************************************************************
		//Ajax handlers

		public function ajax_update_answers() {
		    global $wpdb;

			$result = array( 'status' => '-1',
							 'message' => '',
							 'percent_answered' => [] );

			//Validate expected params
			if ( $_POST['quiz_id'] == null ) {
                $result["message"] = "ajax_update_answers: quiz_id was null";
				die(json_encode($result));
            }
            if ( $_POST['question_no'] == null ) {
                $result["message"] = "ajax_update_answers: question_no was null";
                die(json_encode($result));
            }
            if ( $_POST['answer_no'] == null ) {
                $result["message"] = "ajax_update_answers: answer_no was null";
                die(json_encode($result));
            }

            $quiz_id = $_POST['quiz_id'];
            $question_no = $_POST['question_no'];
            $answer_no = $_POST['answer_no'];

            //Update user vote
            $wpdb->query($wpdb->prepare("
                INSERT INTO ".$wpdb->base_prefix."mission_quiz_answer_totals
                    (quiz_id, question_id, answer)
                    VALUES (%s, %d, %d)
                    ON DUPLICATE KEY UPDATE answer_total = (answer_total + 1);",
                    $quiz_id, $question_no, $answer_no));

            $answer_totals = $wpdb->get_results($wpdb->prepare("
                SELECT answer, answer_total FROM ".$wpdb->base_prefix."mission_quiz_answer_totals
                    WHERE quiz_id=%s AND question_id=%d
                    ORDER BY answer ASC;",
                $quiz_id, $question_no));

			//Return success
			$result["status"] = 1;
			$result["message"] = "All good";
            $result["percent_answered"] = $answer_totals;

            die(json_encode($result));
		}
	} //class:MissionQuiz

	//Create instance of plugin
	$mission_quiz_plugin = new MissionQuiz();

	//Handle plugin activation and update
	register_activation_hook( __FILE__, array( &$mission_quiz_plugin, 'setup_plugin' ));
	if (function_exists ("register_update_hook"))
		register_update_hook ( __FILE__, array( &$mission_quiz_plugin, 'setup_plugin' ));
	else
		add_action('init', array( &$mission_quiz_plugin, 'setup_plugin' ), 1);

/*
<!--  BEGIN quiz HTML -->
            <div class="outer">
                <div class="quiz-container">
                    <div id="quiz"></div>
                    <div class="button-container">
                        <button id="submitAnswer" class="button1" style="display: inline-block;">Submit Answer</button>
                        <button id="next" class="button1" style="display: none;" fdprocessedid="n2ixpc">Next Question</button>
                        <button id="submit" class="button1" style="display: none;">Submit Quiz</button>
                        <div id="results"></div>
                    </div>
                </div>
            </div>
            <!--  END quiz HTML -->
<script>
  function sendAnswers( quiz_id, question_no, answer_no ) {
      var data = {
        action: 'update_answers',
        quiz_id: quiz_id,
        question_no: question_no,
        answer_no: answer_no
      };
      jQuery.post(MissionQuiz.ajaxurl, data, function(response){ handleUpdateAnswerCallback(response); });
  }

  function handleUpdateAnswerCallback( response ) {
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
                            <div class="results-container">
                                <div class="checkmark">&nbsp ✓</div>
                                <div class="wrong">&nbsp X</div>
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
        await sleep(2000);

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

            if (userAnswer !== 'undefined') {
                if (inputs[i].value === userAnswer) {
                    answer_no = i;
                }
            }

            var mark;
            const resultsContainer = answerContainer.querySelectorAll('.results-container')[i];
            const percent = resultsContainer.querySelector(`.percent`);
            if (inputs[i].value === myQuestions[questionNumberX].correctAnswer) {
                inputs[i].parentElement.classList.add("correct");

                mark = resultsContainer.querySelector(`.checkmark`);
                percent.style.backgroundColor = "LightGreen";
            }
            else {
                if (inputs[i].value === userAnswer) {
                    inputs[i].parentElement.classList.add("incorrect");
                    mark = resultsContainer.querySelector('.wrong');
                }

                percent.style.backgroundColor = "LightCoral";
            }

            if (mark !== undefined) {
                mark.style.display = "inline-block";
            }
        }

        if (userAnswer === undefined) {
            for (var i = 0; i < inputs.length; i++) {
                const resultsContainer = answerContainer.querySelectorAll('.results-container')[i];
                if (inputs[i].value != myQuestions[questionNumberX].correctAnswer) {
                    inputs[i].parentElement.classList.add("incorrect")
                    const wrong = resultsContainer.querySelector(`.wrong`);
                    wrong.style.display = "inline-block";
                }
            }
        }

        sendAnswers(post_id, questionNumberX, answer_no);

        resultsContainer.style.display = "inherit";

        // count correct answers
        if (myQuestions[questionNumberX].correctAnswer === userAnswer) {
            numCorrect++;
        }
        else {
            incorrectAnswers.push(questionNumberX)
        }
    }

    const quizContainer = document.getElementById('quiz');
    const resultsContainer = document.getElementById('results');
    const submitButton = document.getElementById('submit');
    const submitAnswerButton = document.getElementById('submitAnswer');

    // define quiz data
    const myQuestions = [
        {
            "question": "1. State eviction protections expire April 1. As of last Wednesday, how many of the inner Mission’s rent relief requests had been paid?",
            "link": "https://missionlocal.org/2022/03/as-april-1-evictions-loom-only-44-of-mission-rent-relief-requests-have-been-paid/",
            "image": "https://i0.wp.com/missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/03/cancelrent.jpg?w=1200&ssl=1",
            "answers": { a: "10%", b: "44%", c: "80%", d: "98%" },
            "percentAnswered": [25, 45, 10, 20],
            "correctAnswer": "b"
        },
        {
            "question": "2. A new Valencia Street gallery is bringing the Palestinian refugee experience into focus – through which medium?",
            "link": "https://missionlocal.org/2022/03/a-new-valencia-street-gallery-brings-refugee-experience-into-focus/",
            "image": "https://i0.wp.com/missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/03/jehad-photos.jpg?resize=930%2C580&ssl=1",
            "answers": { a: "Photography", b: "Animation", c: "Video", d: "Virtual reality" },
            "percentAnswered": [25, 45, 10, 20],
            "correctAnswer": "a"
        },
        {
            "question": "3. Fill in the blank: According to data presented by Police Chief Bill Scott, San Francisco officers are ___ times more likely to stop and search Black people than white people.",
            "link": "https://missionlocal.org/2022/03/sfpd-stop-and-searches-are-down-but-black-people-are-still-disproportionately-targeted/",
            "image": "https://i0.wp.com/missionloca.s3.amazonaws.com/mission/wp-content/uploads/2017/11/ChiefScott_LowRes_IMG_0123.jpg?w=850&ssl=1",
            "answers": { a: "6", b: "8", c: "10", d: "12" },
            "percentAnswered": [25, 45, 10, 20],
            "correctAnswer": "c"
        },
        {
            "question": "4. Since 2017, which Mission intersection saw the most crashes of any in San Francisco?",
            "link": "https://missionlocal.org/2022/03/the-mission-has-some-of-the-citys-most-dangerous-intersections/",
            "image": "https://i0.wp.com/missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/03/13thAndMissionSt.jpg?w=1200&ssl=1",
            "answers": { a: "13th St. and S Van Ness Ave.", b: "16th St. and Potrero Ave.", c: "13th St. and Mission St.", d: "16th St. and S Van Ness Ave." },
            "percentAnswered": [25, 45, 10, 20],
            "correctAnswer": "c"
        },
        {
            "question": "5. How many job vacancies are there currently in San Francisco city departments?",
            "link": "https://missionlocal.org/2022/03/city-employees-march-against-low-staffing/",
            "image": "https://i0.wp.com/missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/03/StaffingPic6.jpg?resize=1536%2C1139&ssl=1",
            "answers": { a: "800", b: "3,800", c: "8,000", d: "12,000" },
            "percentAnswered": [25, 45, 10, 20],
            "correctAnswer": "b"
        },
        {
            "question": "6. Which company has been renting city property to park their vehicles, in violation of the law?",
            "link": "https://missionlocal.org/2022/03/how-did-amazon-end-up-renting-city-property-to-park-delivery-vans-in-violation-of-the-law/",
            "image": "https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/03/QuestionMarkImage.png",
            "answers": { a: "Uber", b: "Amazon", c: "Lyft", d: "Google" },
            "percentAnswered": [25, 45, 10, 20],
            "correctAnswer": "b"
        },
        {
            "question": "7. Following internal conflicts, the La Raza Community Resource Center was passed over for how much in government rent relief grants last week?",
            "link": "https://missionlocal.org/2022/03/nonprofit-loses-8-million-in-funding-following-internal-controversies/",
            "image": "https://i0.wp.com/missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/03/IMG_8298.jpg?w=1200&ssl=1",
            "answers": { a: "$100,000", b: "$500,000", c: "$2 million", d: "$8 million" },
            "percentAnswered": [25, 45, 10, 20],
            "correctAnswer": "d"
        },
    ];

            // start everything
            buildQuiz();

            var post_id = 0;
            classes = document.body.classList;
            for (let i in classes) {
                if (classes[i].startsWith("postid")) {
                    post_id = classes[i].split("-")[1];
                    break;
                }
            }
            if (post_id === 0) {
                alert("post_id not found");
            }

            var questionNumberX = 0;
            let numCorrect = 0;
            var incorrectAnswers = [];
            var PercentAnswered = [];

            // pagination
            const nextButton = document.getElementById("next");
            const slides = document.querySelectorAll(".slide");
            let currentSlide = 0;

            // show first slide
            showSlide(currentSlide);

            submitAnswerButton.addEventListener("click", validateAnswers);
            submitAnswerButton.addEventListener("click", showAnswer);
            submitButton.addEventListener('click', showResults);
            nextButton.addEventListener("click", showNextSlide);
            </script>
            <!--  END quiz javascript -->*/

}

?>
