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
 * Copyright 2023 Craig Mautner (mailto:craig.mautner@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA    02110-1301    USA
 *
 * This Wordpress plugin is released under a GNU General Public License, version 2.
 * A complete version of this license can be found here:
 * http://www.gnu.org/licenses/gpl-2.0.html
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

        // Called on activation or update
        public function setup_plugin() {
            global $wpdb;
            global $mission_quiz_db_version;
            require_once( ABSPATH . 'wp-admin/upgrade-functions.php' );

            // if the option is already set then setup not required
            if (get_option("mission_quiz_db_version")) {
                if (get_option("mission_quiz_db_version") != $mission_quiz_db_version) {
                    die ("downgrade not supported!!!"); // there is no prev version using this var
                }
                return;
            }

            // create the database, will fail if it already exists
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

            // set the option to skip setup next time
            update_option ("mission_quiz_db_version", $mission_quiz_db_version);
        }

        public function init_plugin() {
            if ( !is_admin() ) {
                // load the style sheet
                wp_register_style( 'mission-quiz', plugins_url( '/style/mission-quiz.css', __FILE__));
                wp_enqueue_style( 'mission-quiz' );

                // load the javascript
                wp_register_script( 'mission-quiz',
                        plugins_url( '/js/mission-quiz.js', __FILE__),
                        array( 'jquery' ),
                        '1.0'
                    );
                 wp_enqueue_script('mission-quiz');
            }
        }

        public function add_ajax_url()
        {
            echo '<script type="text/javascript">var MissionQuiz = { ajaxurl: "'.admin_url ('admin-ajax.php').'" };</script>';
        }

        //********************************************************************
        // Ajax handler, called when javascript calls jQuery.post(MissionQuiz.ajaxurl, data, ...)
        // with data.action='update_answers'
        public function ajax_update_answers() {
            global $wpdb;  // database handle

            // return object. Will be passed to the jQuery callback as json
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

            //Update user vote, $answer_no=-1 means no answer selected
            if ($answer_no >= 0) {
                // Either create a new entry (answer_total defaults to 1) or increment the
                // existing entry (UPDATE answer_total = (answer_total + 1);)
                $wpdb->query($wpdb->prepare("
                    INSERT INTO ".$wpdb->base_prefix."mission_quiz_answer_totals
                        (quiz_id, question_id, answer)
                        VALUES (%s, %d, %d)
                        ON DUPLICATE KEY UPDATE answer_total = (answer_total + 1);",
                        $quiz_id, $question_no, $answer_no));
            }

            // Retrieve all of the answer_totals for this quiz/question/answer combination
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


/* Modify myQuestions[] below, then add the following to your Wordpress page using the HTML widget.
<!--  BEGIN quiz HTML -->

        <div class="outer">
            <div class="quiz-container" id="jump-to-next">
                <div id="quiz"></div>
            </div>
            <div id="explanation"></div>
            <div class="button-container">
                <button id="next" class="button1 hide" onclick="plusSlides(1)">Next</button>
                <div id="results"></div>
            </div>
        </div>

        <script>
            ////
            //// FUNCTION: BUILD QUIZ
            ////
            function buildQuiz(){
                const output = [];
                myQuestions.forEach(
                    (currentQuestion, questionNumber) => {
                    const answers = [];
                    for(letter in currentQuestion.answers){
                        // create the answer buttons
                        answers.push(
                        `<label>
                            <input class="button-answers button-answers-hover" type="button" name="${questionNumber}" id="${currentQuestion.answers[letter]}" value="${currentQuestion.answers[letter]}">
                        </label>`
                        );
                        }
                        // create the slide
                        output.push(
                            `<div class="slide">
                            <img decoding="async" class="image jetpack-lazy-image" src="${currentQuestion.image}" data-lazy-src="http://$currentQuestion.image?is-pending-load=1" srcset="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"><noscript><img data-lazy-fallback="1" decoding="async" class="image" src="${currentQuestion.image}" /></noscript>
                            <div class="question"><p><strong>${currentQuestion.number}. ${currentQuestion.question}</strong></p></div>
                            ${answers.join("")}
                            </div>`
                        );
                    });
                quizContainer.innerHTML = output.join('');
            };
            ////
            //// QUIZ DATA
            ////
            const myQuestions = [
                {
                "number": "1",
                "question":"Which Mission personality is leaving San Francisco?",
                "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/placeholder.jpg",
                "answers": {a:"Lone Star Swan",b:"Manny Yekutiel",c:"The Crème Brûlée Cart Man"},
                "correctAnswer":"The Crème Brûlée Cart Man",
                "explanation": "Curtis Kimball, also known as the The Crème Brûlée Cart Man/The Pancake Guy, is <a target='_blank' href='https://missionlocal.org/2022/07/creme-brulee-cart-pancake-curtis-kimball/'>leaving San Francisco after two decades to be closer to ailing family</a>. He said goodbye with a final pancake event on Saturday.<br /><br />Sadly, <a target='_blank' href='https://missionlocal.org/2022/02/swan-song-john-ratliff-lone-star-swan-dies-at-81/'>Lone Star Swan</a> died earlier this year."
                },
                {
                "number": "2",
                "question":"Gig Workers Rising is the biggest group trying to organize gig workers in Northern California. Roughly how many members do they have?",
                "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/featured-image-1-1536x1184.jpg",
                "answers": {a:"20",b:"200",c:"2,000"},
                "correctAnswer":"200",
                "explanation": "Volunteer organizers have been trying to <a target='_blank' href='https://missionlocal.org/2022/07/labor-organizers-sf-gig-workers-tough-target/'>reach out to gig workers</a> to ultimately claim the right to strike, protest, and make demands of employers. Gig workers have far fewer protections than regular employees and have not been able to organize unions in the same way."
                },
                {
                "number": "3",
                "question":"When District Attorney Brooke Jenkins gave a speech in Chinatown, the event’s translator took some liberties with their Cantonese translation. What did the translator say?",
                "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/IMG_9868.jpg",
                "answers": {a:"Jenkins will bring “bandits” to justice",b:"Jenkins will continue the legacy of Chesa Boudin",c:"Jenkins will pursue responsible reform"},
                "correctAnswer":"Jenkins will bring “bandits” to justice",
                "explanation": "<a target='_blank' href='https://missionlocal.org/2022/07/da-brooke-jenkins-chinatown-scourge-anti-asisan-bandits/'>Jenkins said</a> “no longer will we just allow people to walk around feeling like they’re going to be targeted because of who they are,” which was translated into a promise to “bring all the bandits who attacked our Asians and attacked our community to justice.”"
                },
                {
                "number": "4",
                "question":"Why were transfers from Laguna Honda hospital halted yesterday?",
                "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/05/Screen-Shot-2022-05-17-at-2.55.12-PM-1536x960.png",
                "answers": {a:"Patients’ families staged a hunger strike",b:"Other facilities refused to take patients",c:"Several patients died after moving"},
                "correctAnswer":"Several patients died after moving",
                "explanation": "Pleas from families and advocates to <a target='_blank' href='https://missionlocal.org/2022/07/transfers-from-laguna-honda-put-on-hold-but-not-before-at-least-five-patient-deaths/'>halt patient transfers</a> out of Laguna Honda Hospital were heard after at least five patients died right after they were transfered."
                },
                {
                "number": "5",
                "question":"Some 300 workers from which SF housing manager went on strike this Wednesday?",
                "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/Strike_banner_blurred.jpg",
                "answers": {a:"Tenderloin Neighborhood Development Corporation",b:"Tenderloin Housing Clinic",c:"Caritas Management"},
                "correctAnswer":"Tenderloin Housing Clinic",
                "explanation": "The workers were <a target=’_blank’ href=’https://missionlocal.org/2022/07/tenderloin-housing-clinic-workers-walk-off-job-demand-living-wage/’>demanding higher wages</a> for all of their staff. The Tenderloin Housing Clinic management said that the wages they can give are dependent on money provided by the city."
                },
                {
                "number": "6",
                "question":"How did Mission resident Anand Upender get to know his neighbors?",
                "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/placeholder.jpg",
                "answers": {a:"Launched an oral history project",b:"Served coffee out of his garage",c:"Performed 12 hours of interpretive dance"},
                "correctAnswer":"Served coffee out of his garage",
                "explanation": "Upender and his roommates didn’t want to be “the kind of transient young people who move through cities” quickly, he said, prompting his <a target='_blank' href=https://missionlocal.org/2022/07/york-street-coffee-pop-up-mission/''>coffee-based community building</a>."
                },
                {
                "number": "7",
                "question":"Who created this linoleum print, featured in a recently opened Richmond exhibition?",
                "image":"https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2022/07/My-project-3-11-612x640.png",
                "answers": {a:"Diego Rivera",b:"Frida Kahlo",c:"Emmy Lou Packard"},
                "correctAnswer":"Emmy Lou Packard",
                "explanation": "Packard was a friend and ally of Mexican muralist Diego Rivera and his wife Frida Kahlo, but produced a <a target='_blank' href=’https://missionlocal.org/2022/07/who-is-emmy-lou-packard/'>plenty of her own work too</a>. Some of her best works will be on display at the Richmond Arts Center until August 20."
                },
            ];
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
            ////
            //// INITIALIZE
            ////
            // grab quiz HTML and build the quiz
            const quizContainer = document.getElementById('quiz');
            buildQuiz();
            questionIterate = -1;
            numCorrect = 0;
            // on answer click
            $(".button-answers").click(function() {
                $('#next').removeClass('hide');
                var userAnswer = $(this).attr('id');
                var correctAnswer = myQuestions[questionIterate].correctAnswer;
                var userAnswerButton = document.getElementById(userAnswer);
                var correctAnswerButton = document.getElementById(correctAnswer);
                validateAnswers(userAnswer, correctAnswer, userAnswerButton, correctAnswerButton);
            });
            // start off slides
            let slideIndex = 0;
            showSlides(slideIndex);
        </script>
	<footer class="entry-footer">
		<span class="tags-links"><span>Tagged: </span><a href="https://missionlocal.org/tag/quiz/" rel="tag">quiz</a></span>	</footer><!-- .entry-footer -->


			<div class="author-bio">
				<img alt="Avatar photo" src="https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2021/09/willJarrett_profile-80x80.jpg" class="avatar avatar-80 photo jetpack-lazy-image jetpack-lazy-image--handled" height="80" width="80" srcset="https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2021/09/willJarrett_profile.jpg 2x" data-lazy-loaded="1" loading="eager"><noscript><img data-lazy-fallback="1" alt='Avatar photo' src='https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2021/09/willJarrett_profile-80x80.jpg' srcset='https://missionloca.s3.amazonaws.com/mission/wp-content/uploads/2021/09/willJarrett_profile.jpg 2x' class='avatar avatar-80 photo' height='80' width='80' /></noscript>
				<div class="author-bio-text">
					<div class="author-bio-header">
						<div>
							<h2 class="accent-header">
								Will Jarrett							</h2>

															<div class="author-meta">
																			<a class="author-email" href="mailto:Will@MissionLocal.com">
											<svg class="svg-icon" width="18" height="18" aria-hidden="true" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"></path><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"></path></svg>											Will@MissionLocal.com										</a>
																		<ul class="author-social-links"><li class="twitter"><a href="https://twitter.com/WillJarrettData" target="_blank"><svg class="svg-icon" width="24" height="24" aria-hidden="true" role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.23,5.924c-0.736,0.326-1.527,0.547-2.357,0.646c0.847-0.508,1.498-1.312,1.804-2.27 c-0.793,0.47-1.671,0.812-2.606,0.996C18.324,4.498,17.257,4,16.077,4c-2.266,0-4.103,1.837-4.103,4.103 c0,0.322,0.036,0.635,0.106,0.935C8.67,8.867,5.647,7.234,3.623,4.751C3.27,5.357,3.067,6.062,3.067,6.814 c0,1.424,0.724,2.679,1.825,3.415c-0.673-0.021-1.305-0.206-1.859-0.513c0,0.017,0,0.034,0,0.052c0,1.988,1.414,3.647,3.292,4.023 c-0.344,0.094-0.707,0.144-1.081,0.144c-0.264,0-0.521-0.026-0.772-0.074c0.522,1.63,2.038,2.816,3.833,2.85 c-1.404,1.1-3.174,1.756-5.096,1.756c-0.331,0-0.658-0.019-0.979-0.057c1.816,1.164,3.973,1.843,6.29,1.843 c7.547,0,11.675-6.252,11.675-11.675c0-0.178-0.004-0.355-0.012-0.531C20.985,7.47,21.68,6.747,22.23,5.924z"></path><title>twitter</title></svg></a></li></ul>								</div><!-- .author-meta -->

						</div>
					</div><!-- .author-bio-header -->

											<p>DATA REPORTER. Will was born in the UK and studied English at Oxford University. After a few years in publishing, he absconded to the USA where he studied data journalism in New York. Will has strong views on healthcare, the environment, and the Oxford comma.</p>

						<a class="author-link" href="https://missionlocal.org/author/wjarrett/" rel="author">
							More by Will Jarrett						</a>

				</div><!-- .author-bio-text -->

			</div><!-- .author-bio -->

</article>*/

}

?>
