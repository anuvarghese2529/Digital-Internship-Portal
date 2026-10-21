<?php
/**
 * NaiveBayes.php
 * -------------------------------------------------------------
 * A small, self-contained Multinomial Naive Bayes TEXT CLASSIFIER,
 * implemented from scratch (no external ML library). This is the
 * "AI / ML" part of the chatbot: it is trained on labeled example
 * sentences (config/chatbot_intents.php) and then predicts the
 * intent of a brand-new sentence it has never seen before.
 *
 * HOW IT WORKS (useful to know for your viva / report):
 *
 *  1. TRAINING (train()):
 *     For every training sentence, we split it into words ("tokens").
 *     For each intent (class) we count:
 *       - how many times each word appears in that class -> wordCounts
 *       - how many total words that class has        -> classWordTotal
 *       - how many training sentences belong to it    -> classDocCount
 *     We also build the full vocabulary (every unique word seen).
 *
 *  2. PREDICTION (predict()):
 *     For a new sentence, for every class we compute:
 *
 *         log P(class | words) ∝ log P(class) + Σ log P(word | class)
 *
 *     P(class)        = classDocCount[class] / totalDocs      (the "prior")
 *     P(word | class) = (wordCounts[class][word] + 1) /
 *                       (classWordTotal[class] + vocabularySize)
 *                       <- Laplace (add-1) smoothing so unseen words
 *                          don't make the whole probability zero.
 *
 *     The class with the highest log-probability wins. We also turn
 *     the log-probabilities into a normal 0-1 "confidence" score using
 *     the softmax function, so the app can decide whether the model
 *     is confident enough to answer, or should say "I'm not sure".
 *
 * This is a legitimate, standard supervised machine learning
 * algorithm (Naive Bayes classification over a bag-of-words model) —
 * it is genuinely learning statistical word/intent associations from
 * data, not just matching hardcoded keywords.
 */

class NaiveBayes
{
    /** @var array<string,array<string,int>> word counts per class: [class][word] => count */
    private array $wordCounts = [];

    /** @var array<string,int> total number of word occurrences per class */
    private array $classWordTotal = [];

    /** @var array<string,int> number of training documents per class */
    private array $classDocCount = [];

    /** @var array<string,bool> the full vocabulary across all classes */
    private array $vocabulary = [];

    /** @var int total number of training documents, across all classes */
    private int $totalDocs = 0;

    /** @var array<string> minimal stopword list — words too common to carry meaning */
    private array $stopwords = [
        "a", "an", "the", "is", "are", "am", "do", "does", "did",
        "i", "me", "my", "you", "your", "it", "to", "of", "for",
        "in", "on", "at", "and", "or", "please", "can", "could",
        "would", "will", "what", "how", "about",
    ];

    /**
     * Lowercase, strip punctuation, split into words, drop stopwords
     * and very short leftovers. Returns a plain array of tokens.
     */
    public function tokenize(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', trim($text));

        $tokens = [];
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            if (in_array($word, $this->stopwords, true)) {
                continue;
            }
            $tokens[] = $word;
        }

        return $tokens;
    }

    /**
     * Train the classifier.
     *
     * @param array<string,array<string>> $trainingData  [intent => [example sentences]]
     */
    public function train(array $trainingData): void
    {
        foreach ($trainingData as $class => $examples) {
            if (!isset($this->wordCounts[$class])) {
                $this->wordCounts[$class] = [];
                $this->classWordTotal[$class] = 0;
                $this->classDocCount[$class] = 0;
            }

            foreach ($examples as $sentence) {
                $this->classDocCount[$class]++;
                $this->totalDocs++;

                foreach ($this->tokenize($sentence) as $word) {
                    $this->vocabulary[$word] = true;
                    $this->classWordTotal[$class]++;

                    if (!isset($this->wordCounts[$class][$word])) {
                        $this->wordCounts[$class][$word] = 0;
                    }
                    $this->wordCounts[$class][$word]++;
                }
            }
        }
    }

    /**
     * Predict the intent of a new message.
     *
     * @return array{intent: string, confidence: float, scores: array<string,float>}
     */
    public function predict(string $text): array
    {
        $tokens = $this->tokenize($text);
        $vocabSize = max(count($this->vocabulary), 1);

        // If NONE of the words in this message were ever seen during
        // training, there is no real signal to classify on — every class
        // would just fall back to its prior probability, which produces a
        // misleadingly confident-looking but meaningless "top" class.
        // In that case, be honest and say we don't know.
        $knownTokens = 0;
        foreach ($tokens as $word) {
            if (isset($this->vocabulary[$word])) {
                $knownTokens++;
            }
        }

        if (!empty($tokens) && $knownTokens === 0) {
            return [
                "intent" => "fallback",
                "confidence" => 0.0,
                "scores" => [],
            ];
        }

        $logScores = [];

        foreach ($this->classDocCount as $class => $docCount) {

            // Prior: P(class) — how common this intent is in the training set
            $logProb = log($docCount / $this->totalDocs);

            foreach ($tokens as $word) {
                $wordCount = $this->wordCounts[$class][$word] ?? 0;
                $classTotal = $this->classWordTotal[$class];

                // Likelihood: P(word | class), with Laplace (add-1) smoothing
                $probWordGivenClass = ($wordCount + 1) / ($classTotal + $vocabSize);

                $logProb += log($probWordGivenClass);
            }

            $logScores[$class] = $logProb;
        }

        if (empty($logScores) || empty($tokens)) {
            return [
                "intent" => "fallback",
                "confidence" => 0.0,
                "scores" => [],
            ];
        }

        // Convert log-scores to a 0..1 confidence distribution (softmax),
        // shifting by the max log-score first to avoid overflow/underflow.
        $maxLog = max($logScores);
        $expSum = 0.0;
        $expScores = [];

        foreach ($logScores as $class => $logProb) {
            $expScores[$class] = exp($logProb - $maxLog);
            $expSum += $expScores[$class];
        }

        $confidenceScores = [];
        foreach ($expScores as $class => $expVal) {
            $confidenceScores[$class] = $expVal / $expSum;
        }

        arsort($confidenceScores);
        $bestClass = array_key_first($confidenceScores);

        return [
            "intent" => $bestClass,
            "confidence" => $confidenceScores[$bestClass],
            "scores" => $confidenceScores,
        ];
    }
}
