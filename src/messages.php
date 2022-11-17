<?php

function getStyleForAlert($messagesGroup)
{
    if ($messagesGroup === 'success') {
        return 'alert-success';
    };
    if ($messagesGroup === 'warning') {
        return 'alert-info';
    };
    return 'alert-danger';
}

function addMessagesToParams($messages, $params = [])
{
    return empty($messages) ? $params : [...$params, 'messages' => $messages];
}
