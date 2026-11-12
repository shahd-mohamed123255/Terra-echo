<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    protected $fillable = ['user_id', 'farm_id', 'agent_conversation_id'];
}