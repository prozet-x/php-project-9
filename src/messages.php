<?php

function getStyleForAlert(string $messagesGroup)
{
    if ($messagesGroup === 'success') {
        return 'alert-success';
    };
    if ($messagesGroup === 'warning') {
        return 'alert-info';
    };
    return 'alert-danger';
}

function addMessagesToParams(array $messages, array $params = [])
{
    return empty($messages) ? $params : [...$params, 'messages' => $messages];
}
