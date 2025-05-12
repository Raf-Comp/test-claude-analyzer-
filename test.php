<?php
/**
 * Przykładowy plik PHP do analizy
 * 
 * Ten plik zawiera podstawową funkcjonalność
 * obliczania statystyk dla podanego tekstu.
 */

class TextAnalyzer {
    private $text;
    
    public function __construct($text = '') {
        $this->text = $text;
    }
    
    public function setText($text) {
        $this->text = $text;
    }
    
    public function getWordCount() {
        return str_word_count($this->text);
    }
    
    public function getCharacterCount() {
        return strlen($this->text);
    }
    
    public function getSentenceCount() {
        return preg_match_all('/[.!?]+/', $this->text, $matches);
    }
    
    public function getStatistics() {
        return [
            'word_count' => $this->getWordCount(),
            'character_count' => $this->getCharacterCount(),
            'sentence_count' => $this->getSentenceCount(),
            'average_word_length' => $this->getCharacterCount() / ($this->getWordCount() ?: 1)
        ];
    }
}

// Przykład użycia
$analyzer = new TextAnalyzer("To jest przykładowy tekst. Ma on kilka zdań. Służy do testów!");
$stats = $analyzer->getStatistics();

// Funkcje pomocnicze
function formatStatistics($statistics) {
    return "Statystyki tekstu:\n" .
           "- Liczba słów: {$statistics['word_count']}\n" .
           "- Liczba znaków: {$statistics['character_count']}\n" .
           "- Liczba zdań: {$statistics['sentence_count']}\n" .
           "- Średnia długość słowa: " . round($statistics['average_word_length'], 2) . " znaków";
}
