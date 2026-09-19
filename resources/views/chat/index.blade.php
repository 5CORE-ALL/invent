@extends('layouts.vertical', ['title' => 'Invent Chat', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .content-page .content { padding-bottom: 0; }
        .content-page .content .container-fluid { padding-left: 0; padding-right: 0; padding-bottom: 0; }
        .page-title-box { display: none; }
        .slack {
            display: flex;
            height: calc(100vh - 78px);
            min-height: 540px;
            background: #1a1d21;
            overflow: hidden;
        }
        .slack-nav {
            width: 260px;
            flex-shrink: 0;
            background: #3f0e40;
            color: #cfc3cf;
            display: flex;
            flex-direction: column;
        }
        .slack-nav__ws {
            padding: 14px 16px 12px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            font-weight: 800;
            color: #fff;
            font-size: 17px;
        }
        .slack-nav__search {
            margin: 10px 12px 0;
        }
        .slack-nav__search input {
            width: 100%;
            border: 0;
            border-radius: 6px;
            background: rgba(0,0,0,.25);
            color: #fff;
            padding: 7px 10px;
            font-size: 13px;
        }
        .slack-nav__search input::placeholder { color: #b9a8b9; }
        .slack-nav__scroll { flex: 1; overflow: auto; padding: 10px 0 16px; }
        .slack-sec {
            padding: 12px 16px 4px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #ab9bab;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .slack-sec button {
            border: 0;
            background: transparent;
            color: #cfc3cf;
            font-size: 16px;
            line-height: 1;
            padding: 0;
        }
        .slack-item {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            border: 0;
            background: transparent;
            color: #cfc3cf;
            text-align: left;
            padding: 5px 16px;
            font-size: 14px;
        }
        .slack-item:hover { background: rgba(0,0,0,.18); color: #fff; }
        .slack-item.is-active { background: #1164a3; color: #fff; }
        .slack-item.is-unread { font-weight: 800; color: #fff; }
        .slack-item__hash { width: 14px; opacity: .7; }
        .slack-item img { width: 20px; height: 20px; border-radius: 4px; object-fit: cover; }
        .slack-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #2bac76; box-shadow: 0 0 0 2px rgba(43,172,118,.2);
            flex-shrink: 0;
        }
        .slack-dot.is-off { background: transparent; border: 1.5px solid #ab9bab; box-shadow: none; }
        .slack-item__name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .slack-item__badge {
            min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
            background: #cd2553; color: #fff; font-size: 10px; font-weight: 800; line-height: 18px; text-align: center;
        }
        .slack-main { flex: 1; min-width: 0; display: flex; flex-direction: column; background: #fff; }
        .slack-head {
            padding: 10px 18px;
            border-bottom: 1px solid #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .slack-head__name { margin: 0; font-size: 16px; font-weight: 800; color: #1d1c1d; }
        .slack-head__sub { margin: 2px 0 0; font-size: 12px; color: #616061; }
        .slack-feed { flex: 1; overflow: auto; padding: 12px 0 8px; }
        .slack-empty { color: #616061; text-align: center; margin-top: 18vh; }
        .slack-day {
            display: flex; align-items: center; gap: 12px;
            margin: 16px 20px 8px; color: #1d1c1d; font-size: 12px; font-weight: 800;
        }
        .slack-day::before, .slack-day::after { content: ''; flex: 1; height: 1px; background: #e8e8e8; }
        .slack-new {
            display: flex; align-items: center; gap: 10px;
            margin: 10px 20px; color: #e01e5a; font-size: 12px; font-weight: 800;
        }
        .slack-new::before, .slack-new::after { content: ''; flex: 1; height: 1px; background: #e01e5a; }
        .slack-msg { display: flex; gap: 10px; padding: 6px 20px; position: relative; }
        .slack-msg:hover { background: #f8f8f8; }
        .slack-msg__avatar { width: 36px; height: 36px; border-radius: 6px; object-fit: cover; background: #3f0e40; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px; flex-shrink: 0; }
        .slack-msg__meta { font-size: 12px; color: #616061; }
        .slack-msg__meta strong { color: #1d1c1d; font-size: 14px; margin-right: 6px; }
        .slack-msg__body { color: #1d1c1d; font-size: 15px; line-height: 1.45; word-break: break-word; }
        .invent-chat-mention { color: #1264a3; background: #e8f5fa; border-radius: 4px; padding: 0 3px; font-weight: 700; }
        .slack-fwd {
            border-left: 3px solid #e01e5a;
            margin: 6px 0;
            padding: 4px 0 4px 10px;
            color: #616061;
            font-size: 13px;
        }
        .slack-fwd strong { color: #1d1c1d; }
        .slack-msg__file { margin-top: 8px; }
        .slack-msg__file img { max-width: 320px; max-height: 240px; border-radius: 8px; }
        .slack-msg__seen { font-size: 11px; color: #616061; margin-top: 3px; }
        .slack-msg__seen.is-seen { color: #007a5a; font-weight: 700; }
        .slack-msg__act {
            display: none; position: absolute; top: 4px; right: 16px;
            background: #fff; border: 1px solid #ddd; border-radius: 6px; overflow: hidden;
        }
        .slack-msg:hover .slack-msg__act { display: flex; }
        .slack-msg__act button {
            border: 0; background: #fff; padding: 4px 8px; font-size: 12px; font-weight: 700; color: #1d1c1d;
        }
        .slack-composer { padding: 0 16px 16px; }
        .slack-composer__box {
            border: 1px solid #c9c9c9;
            border-radius: 8px;
            padding: 8px 10px 6px;
        }
        .slack-composer textarea {
            width: 100%; border: 0; resize: none; min-height: 44px; max-height: 140px; outline: none; font-size: 15px;
        }
        .slack-composer__row { display: flex; align-items: center; justify-content: space-between; }
        .slack-send {
            border: 0; border-radius: 6px; background: #007a5a; color: #fff; font-weight: 700; padding: 5px 12px;
        }
        .slack-send:disabled { background: #d0d0d0; }
        .slack-msg.is-pending { opacity: 0.65; }
        .slack-pick {
            display: none; position: absolute; left: 12px; right: 12px; top: 86px; z-index: 6;
            background: #fff; color: #1d1c1d; border-radius: 8px; max-height: 260px; overflow: auto;
            box-shadow: 0 12px 30px rgba(0,0,0,.25);
        }
        .slack-pick.is-open { display: block; }
        .slack-pick button {
            display: flex; width: 100%; border: 0; background: #fff; text-align: left; padding: 8px 10px; gap: 8px; align-items: center;
        }
        .slack-pick button:hover { background: #f8f8f8; }
        .slack-pick img { width: 22px; height: 22px; border-radius: 4px; }
        .slack-status { font-size: 11px; padding: 4px 16px 8px; color: #ab9bab; }
        .slack-status.is-off { color: #ffb3b3; }
        .slack-status.is-wait { color: #f2c744; }
        .slack-head__tools { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        .slack-head__tools button, .slack-head__tools select {
            border: 1px solid #ddd; background: #fff; border-radius: 6px; font-size: 12px; padding: 3px 8px;
        }
        .slack-pinbar { display: none; padding: 6px 18px; border-bottom: 1px solid #eee; background: #fff8e1; font-size: 13px; }
        .slack-pinbar.is-on { display: block; }
        .slack-pinbar button { border: 0; background: transparent; color: #1d1c1d; }
        .slack-reply { display: none; font-size: 12px; color: #616061; padding: 0 4px 6px; }
        .slack-reply.is-on { display: block; }
        .slack-typing { min-height: 18px; font-size: 12px; color: #616061; padding: 0 20px 4px; }
        .slack-msg__act { flex-wrap: wrap; max-width: 280px; }
        .slack-msg.is-failed { opacity: 1; background: #fff5f5; }
        .slack-thread {
            display: none; width: 340px; border-left: 1px solid #e8e8e8; background: #fff;
            flex-direction: column;
        }
        .slack-thread.is-open { display: flex; }
        .slack-thread__h { padding: 12px 14px; border-bottom: 1px solid #e8e8e8; font-weight: 800; }
        .slack-thread__feed { flex: 1; overflow: auto; }
        .slack-mention-pick {
            display: none; position: absolute; left: 16px; right: 16px; bottom: 110px; z-index: 8;
            background: #fff; border: 1px solid #ddd; border-radius: 8px; max-height: 180px; overflow: auto;
        }
        .slack-mention-pick.is-open { display: block; }
        .slack-mention-pick button { display: block; width: 100%; border: 0; background: #fff; text-align: left; padding: 6px 10px; }
        .slack-mention-pick button:hover { background: #f8f8f8; }
        .slack-back { display: none; border: 0; background: transparent; font-weight: 800; margin-right: 8px; }
        .slack-install { display: none; margin: 8px 12px; width: calc(100% - 24px); border: 0; border-radius: 6px; background: #1164a3; color: #fff; font-weight: 700; padding: 8px; }
        .slack-install.is-on { display: block; }
        .slack-progress { display: none; height: 3px; background: #e8e8e8; margin: 0 16px 8px; }
        .slack-progress.is-on { display: block; }
        .slack-progress span { display: block; height: 100%; width: 0; background: #007a5a; }
        body.invent-chat-page .page-title-box,
        body.invent-chat-page .footer { display: none !important; }
        @media (max-width: 992px) {
            body.invent-chat-page .navbar-custom,
            body.invent-chat-page .leftside-menu,
            body.invent-chat-page .mobile-header { display: none !important; }
            body.invent-chat-page .content-page { margin-left: 0 !important; padding-top: 0 !important; }
            .slack {
                height: 100dvh;
                min-height: 100dvh;
                position: relative;
            }
            .slack-nav, .slack-main, .slack-thread {
                position: absolute; inset: 0; width: 100%; height: 100%;
            }
            .slack-main, .slack-thread { display: none; }
            .slack.is-room .slack-nav { display: none; }
            .slack.is-room .slack-main { display: flex; }
            .slack.is-thread .slack-main { display: none; }
            .slack.is-thread .slack-thread { display: flex; width: 100%; border-left: 0; }
            .slack-back { display: inline-block; }
            .slack-head { padding: 10px 12px; padding-top: calc(10px + env(safe-area-inset-top)); }
            .slack-head__tools { justify-content: flex-end; }
            .slack-head__tools button, .slack-head__tools select { font-size: 11px; padding: 6px 8px; min-height: 36px; }
            .slack-composer { padding: 0 10px calc(12px + env(safe-area-inset-bottom)); }
            .slack-composer textarea { font-size: 16px; min-height: 48px; }
            .slack-msg { padding: 8px 12px; }
            .slack-msg__file img { max-width: 100%; height: auto; }
            .slack-msg__act {
                display: none; position: static; margin-top: 8px; max-width: none; flex-wrap: wrap;
            }
            .slack-msg.is-open .slack-msg__act { display: flex; }
            .slack-send { min-width: 72px; min-height: 40px; }
        }
        @media (display-mode: standalone) {
            body.invent-chat-page .navbar-custom,
            body.invent-chat-page .leftside-menu,
            body.invent-chat-page .mobile-header { display: none !important; }
            body.invent-chat-page .content-page { margin-left: 0 !important; padding-top: 0 !important; }
            .slack { height: 100dvh; min-height: 100dvh; }
        }
    </style>
@endsection

@section('content')
    <div class="slack" id="slackApp">
        <aside class="slack-nav">
            <div class="slack-nav__ws">5Core</div>
            <button type="button" class="slack-install" id="slackInstallBtn">Install Invent Chat</button>
            <div class="slack-status" id="slackConn">Connected</div>
            <div class="slack-nav__search">
                <input type="search" id="slackSearch" placeholder="Find people or start a DM" autocomplete="off">
                <div class="slack-pick" id="slackPeopleList"></div>
            </div>
            <div class="slack-nav__scroll">
                <div class="slack-sec">Invent Bot</div>
                <div id="slackBotList"></div>
                <div class="slack-sec">
                    <span>Channels</span>
                    @if ($canManageChannels)
                        <button type="button" id="slackNewChannelBtn" title="New channel">+</button>
                    @endif
                </div>
                <div id="slackChannelList"></div>
                <div class="slack-sec">
                    <span>Groups</span>
                    <button type="button" id="slackNewGroupBtn" title="New group">+</button>
                </div>
                <div id="slackGroupList"></div>
                <div class="slack-sec">
                    <span>Direct messages</span>
                    <button type="button" id="slackNewDmBtn" title="New message">+</button>
                </div>
                <div id="slackDmList"></div>
                <div class="slack-sec">
                    <button type="button" id="slackMarkAllBtn" style="font-size:12px;padding:8px 16px;">Mark all as read</button>
                </div>
            </div>
        </aside>
        <section class="slack-main">
            <div class="slack-head">
                <div class="d-flex align-items-start">
                    <button type="button" class="slack-back" id="slackBackBtn" aria-label="Back">‹</button>
                    <div>
                    <h2 class="slack-head__name" id="slackRoomName">Invent Chat</h2>
                    <p class="slack-head__sub" id="slackRoomSub">Pick a channel or teammate</p>
                    </div>
                </div>
                <div class="slack-head__tools">
                    <button type="button" id="slackSearchOpen">Search</button>
                    <button type="button" id="slackMarkReadBtn">Mark as read</button>
                    <select id="slackStatusSel" title="Presence">
                        <option value="active">Active</option>
                        <option value="away">Away</option>
                        <option value="dnd">Do not disturb</option>
                    </select>
                    <button type="button" id="slackPrefsBtn">Notify</button>
                    @if ($canManageChannels)
                        <a href="{{ route('chat.health') }}" class="btn btn-sm btn-light">Health</a>
                    @endif
                </div>
            </div>
            <div class="slack-pinbar" id="slackPins"></div>
            <div class="slack-feed" id="slackFeed">
                <div class="slack-empty">Select a conversation to start messaging.</div>
            </div>
            <div class="slack-typing" id="slackTyping"></div>
            <div class="slack-progress" id="slackProgress"><span></span></div>
            <form class="slack-composer" id="slackComposer" hidden>
                <div class="slack-reply" id="slackReplyBar"></div>
                <div class="slack-mention-pick" id="slackMentionPick"></div>
                <div class="slack-composer__box">
                    <textarea id="slackBody" rows="2" placeholder="Message"></textarea>
                    <div class="slack-composer__row">
                        <label>
                            <input type="file" id="slackFile" hidden accept="image/*,.jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.xlsx,.xls,.doc,.docx,.zip">
                            <input type="file" id="slackCamera" hidden accept="image/*" capture="environment">
                            <button type="button" class="btn btn-sm btn-light" id="slackAttachBtn">Attach</button>
                            <button type="button" class="btn btn-sm btn-light" id="slackCameraBtn">Camera</button>
                            <span id="slackFileName" class="text-muted small ms-1"></span>
                        </label>
                        <button type="submit" class="slack-send">Send</button>
                    </div>
                </div>
            </form>
        </section>
        <aside class="slack-thread" id="slackThread">
            <div class="slack-thread__h">Thread <button type="button" class="btn btn-sm btn-light float-end" id="slackThreadClose">Close</button></div>
            <div class="slack-thread__feed" id="slackThreadFeed"></div>
        </aside>
    </div>

    <div class="modal fade" id="slackGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="slackGroupForm">
                <div class="modal-header">
                    <h5 class="modal-title">New group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Group name</label>
                    <input class="form-control mb-2" name="name" maxlength="80" placeholder="Ops huddle">
                    <label class="form-label">People</label>
                    <select class="form-select" name="member_ids[]" id="slackGroupMembers" multiple size="10"></select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Create</button>
                </div>
            </form>
        </div>
    </div>

    @if ($canManageChannels)
        <div class="modal fade" id="slackChannelModal" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" id="slackChannelForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Create a channel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Name</label>
                        <input class="form-control mb-2" name="name" required maxlength="80" placeholder="listings">
                        <label class="form-label">Topic</label>
                        <input class="form-control mb-2" name="topic" maxlength="255">
                        <select class="form-select" name="type">
                            <option value="public">Public</option>
                            <option value="private">Private</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Create</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="modal fade" id="slackForwardModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="slackForwardForm">
                <div class="modal-header">
                    <h5 class="modal-title">Forward message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="slackForwardMessageId">
                    <label class="form-label">Send to</label>
                    <select class="form-select" id="slackForwardTarget" required></select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Forward</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="slackSearchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" id="slackSearchForm">
                <div class="modal-header">
                    <h5 class="modal-title">Search chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input class="form-control mb-2" name="q" placeholder="Messages, people, files">
                    <div class="row g-2 mb-2">
                        <div class="col-md-3">
                            <select class="form-select" name="room_type">
                                <option value="">All rooms</option>
                                <option value="dm">DMs</option>
                                <option value="group">Groups</option>
                                <option value="public">Channels</option>
                                <option value="private">Private channels</option>
                            </select>
                        </div>
                        <div class="col-md-3"><select class="form-select" name="user_id" id="slackSearchUser"><option value="">Any person</option></select></div>
                        <div class="col-md-3"><input type="date" class="form-control" name="from"></div>
                        <div class="col-md-3"><input type="date" class="form-control" name="to"></div>
                    </div>
                    <label class="me-3"><input type="checkbox" name="has_attachment" value="1"> Has attachment</label>
                    <label><input type="checkbox" name="has_mention" value="1"> Has mention</label>
                    <div id="slackSearchResults" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Search</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="slackTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="slackTaskForm">
                <div class="modal-header">
                    <h5 class="modal-title">Create task from message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="slackTaskMessageId">
                    <p class="small text-muted" id="slackTaskPreview"></p>
                    <label class="form-label">Assign to</label>
                    <select class="form-select mb-2" id="slackTaskAssignee"></select>
                    <label class="form-label">Due date</label>
                    <input type="date" class="form-control" id="slackTaskDue">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Create task</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="slackPrefsModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="slackPrefsForm">
                <div class="modal-header">
                    <h5 class="modal-title">Notification preferences</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <select class="form-select" id="slackNotifyMode">
                        <option value="all">All messages</option>
                        <option value="mentions">Mentions and threads</option>
                        <option value="dms">Direct messages only</option>
                        <option value="none">None</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
<script>
(function () {
    document.body.classList.add('invent-chat-page');
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const meId = {{ (int) $meId }};
    const canManage = {{ $canManageChannels ? 'true' : 'false' }};
    const canPin = {{ !empty($canPin) ? 'true' : 'false' }};
    const canAnnounce = {{ !empty($canAnnounce) ? 'true' : 'false' }};
    const startChannel = Number(new URLSearchParams(location.search).get('channel') || 0);
    const startMessage = Number(new URLSearchParams(location.search).get('message') || 0);
    const OUTBOX_KEY = 'invent_chat_outbox';
    const DRAFT_KEY = 'invent_chat_draft_';
    const tabBus = ('BroadcastChannel' in window) ? new BroadcastChannel('invent-chat') : null;
    function isMobileChat() {
        return window.matchMedia('(max-width: 992px)').matches || window.matchMedia('(display-mode: standalone)').matches;
    }
    function broadcastChat(payload) {
        if (tabBus) tabBus.postMessage(payload);
    }
    const lists = {
        bot: document.getElementById('slackBotList'),
        channel: document.getElementById('slackChannelList'),
        group: document.getElementById('slackGroupList'),
        dm: document.getElementById('slackDmList'),
    };
    const feed = document.getElementById('slackFeed');
    const composer = document.getElementById('slackComposer');
    const bodyEl = document.getElementById('slackBody');
    const fileEl = document.getElementById('slackFile');
    const fileNameEl = document.getElementById('slackFileName');
    const searchEl = document.getElementById('slackSearch');
    const peopleList = document.getElementById('slackPeopleList');
    let channels = [];
    let directory = [];
    let activeId = 0;
    let lastId = 0;
    let firstUnreadId = 0;
    let initialLoad = true;
    let knownInbox = {};
    let sending = false;
    let syncAbort = null;
    let tickN = 0;
    let replyTo = null;
    let connState = 'online';
    let oldestId = 0;
    let loadingOlder = false;
    const pendingFiles = {};
    const meName = @json($meName);
    const meAvatar = @json(optional(auth()->user())->avatar ? \App\Support\ChatWorkspace::avatarUrl(auth()->user()) : asset('images/users/avatar-2.jpg'));

    const statusSel = document.getElementById('slackStatusSel');
    if (statusSel) statusSel.value = @json($presenceStatus ?? 'active');
    const notifyModeEl = document.getElementById('slackNotifyMode');
    if (notifyModeEl) notifyModeEl.value = @json($notifyMode ?? 'all');

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function clientId() {
        return 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    }

    function setConn(state, label) {
        connState = state;
        const el = document.getElementById('slackConn');
        if (!el) return;
        el.textContent = label || (state === 'online' ? 'Connected' : (state === 'wait' ? 'Reconnecting...' : 'Offline'));
        el.className = 'slack-status' + (state === 'online' ? '' : (state === 'wait' ? ' is-wait' : ' is-off'));
    }

    function readOutbox() {
        try { return JSON.parse(localStorage.getItem(OUTBOX_KEY) || '[]'); } catch (e) { return []; }
    }
    function writeOutbox(rows) {
        localStorage.setItem(OUTBOX_KEY, JSON.stringify(rows.slice(-40)));
    }

    function playTone() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(880, ctx.currentTime);
            o.frequency.linearRampToValueAtTime(1175, ctx.currentTime + 0.07);
            g.gain.setValueAtTime(0.0001, ctx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.07, ctx.currentTime + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.22);
            o.connect(g); g.connect(ctx.destination);
            o.start(); o.stop(ctx.currentTime + 0.24);
        } catch (e) {}
    }

    async function api(url, opts) {
        const extra = opts || {};
        const headers = Object.assign({
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }, extra.headers || {});
        const res = await fetch(url, Object.assign({}, extra, { headers: headers }));
        if (!res.ok) {
            let msg = 'Request failed';
            try { const j = await res.json(); msg = j.message || msg; } catch (e) {}
            throw new Error(msg);
        }
        return res.json();
    }

    function bucket(ch) {
        if (ch.type === 'bot') return 'bot';
        if (ch.type === 'dm') return 'dm';
        if (ch.type === 'group') return 'group';
        return 'channel';
    }

    function renderNav() {
        Object.keys(lists).forEach(function (k) { lists[k].innerHTML = ''; });
        channels.forEach(function (ch) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'slack-item' + (ch.id === activeId ? ' is-active' : '') + (ch.unread > 0 ? ' is-unread' : '');
            const prefix = (ch.type === 'public' || ch.type === 'private') ? '<span class="slack-item__hash">#</span>' : '';
            const avatar = ch.avatar ? '<img src="' + esc(ch.avatar) + '" alt="">' : (ch.type === 'bot' ? '<i class="ri-robot-2-line"></i>' : (ch.type === 'group' ? '<i class="ri-group-line"></i>' : ''));
            const dot = (ch.type === 'dm') ? '<span class="slack-dot' + (ch.online ? '' : ' is-off') + '"></span>' : '';
            const badge = ch.unread > 0 ? '<span class="slack-item__badge">' + ch.unread + '</span>' : '';
            btn.innerHTML = avatar + prefix + dot + '<span class="slack-item__name">' + esc(ch.name) + '</span>' + badge;
            btn.addEventListener('click', function () { openChannel(ch.id); });
            lists[bucket(ch)].appendChild(btn);
        });
        updateTopbar(channels.reduce(function (n, ch) { return n + (ch.unread || 0); }, 0));
        fillForwardTargets();
    }

    function updateTopbar(n) {
        const btn = document.getElementById('chatTopbarBtn');
        if (!btn) return;
        btn.classList.toggle('has-unread', n > 0);
        const el = btn.querySelector('.topbar-chat-btn__count');
        if (el) el.textContent = n > 99 ? '99+' : String(n);
    }

    function fillForwardTargets() {
        const sel = document.getElementById('slackForwardTarget');
        if (!sel) return;
        sel.innerHTML = channels.map(function (ch) {
            const label = (ch.type === 'public' || ch.type === 'private' ? '#' : '') + ch.name;
            return '<option value="' + ch.id + '">' + esc(label) + '</option>';
        }).join('');
    }

    function applyReceipts(receipts) {
        if (!receipts) return;
        Object.keys(receipts).forEach(function (id) {
            const el = document.querySelector('#slack-msg-' + id + ' .slack-msg__seen');
            if (!el) return;
            el.textContent = receipts[id].seen_label || (receipts[id].seen ? 'Seen' : 'Sent');
            el.classList.toggle('is-seen', !!receipts[id].seen);
        });
    }

    function messageCard(m, targetFeed) {
        if (m.client_id) {
            const pending = document.getElementById('slack-msg-' + m.client_id);
            if (pending) pending.remove();
        }
        if (document.getElementById('slack-msg-' + m.id)) {
            const existing = document.getElementById('slack-msg-' + m.id);
            existing.replaceWith(buildMessageEl(m));
            return false;
        }
        const dest = targetFeed || feed;
        dest.appendChild(buildMessageEl(m));
        const numId = Number(m.id);
        if (!Number.isNaN(numId) && numId > 0) {
            lastId = Math.max(lastId, numId);
            if (!oldestId || numId < oldestId) oldestId = numId;
        }
        return true;
    }

    function buildMessageEl(m) {
        const wrap = document.createElement('div');
        wrap.id = 'slack-msg-' + m.id;
        wrap.className = 'slack-msg' + (m.failed ? ' is-failed' : '') + (m.pending ? ' is-pending' : '');
        wrap.dataset.clientId = m.client_id || '';
        const avatar = m.is_bot
            ? '<div class="slack-msg__avatar">@i</div>'
            : '<img class="slack-msg__avatar" src="' + esc(m.avatar || '') + '" alt="">';
        let fwd = '';
        if (m.forwarded) {
            fwd = '<div class="slack-fwd">Forwarded from <strong>' + esc(m.forwarded.name) + '</strong><div>' + esc(m.forwarded.preview || '') + '</div></div>';
        }
        let file = '';
        if (m.attachment_url) {
            file = m.attachment_is_image
                ? '<div class="slack-msg__file"><a href="' + esc(m.attachment_url) + '" target="_blank"><img src="' + esc(m.attachment_url) + '" alt=""></a></div>'
                : '<div class="slack-msg__file"><a href="' + esc(m.attachment_url) + '" target="_blank">' + esc(m.attachment_name || 'File') + '</a></div>';
        }
        const seen = (Number(m.user_id) === meId && m.seen_label)
            ? '<div class="slack-msg__seen' + (m.seen ? ' is-seen' : '') + '">' + esc(m.seen_label) + '</div>'
            : '';
        const edited = m.edited ? ' <span class="text-muted">(edited)</span>' : '';
        const rx = (m.reactions || []).map(function (r) {
            return '<button type="button" class="btn btn-sm btn-light me-1" data-react="' + esc(r.emoji) + '">' + esc(r.emoji) + ' ' + r.count + '</button>';
        }).join('');
        const replies = m.reply_count ? '<button type="button" class="btn btn-link btn-sm p-0" data-thread="' + m.id + '">' + m.reply_count + ' replies</button>' : '';
        const task = m.task_url ? '<div class="small"><a href="' + esc(m.task_url) + '">Open task #' + m.task_id + '</a></div>' : '';
        const own = Number(m.user_id) === meId || canManage;
        const actions = m.deleted ? '' : (
            '<div class="slack-msg__act">' +
            '<button type="button" data-reply="' + m.id + '">Reply</button>' +
            '<button type="button" data-copy="' + m.id + '">Copy</button>' +
            '<button type="button" data-fwd="' + m.id + '">Forward</button>' +
            '<button type="button" data-reactbtn="' + m.id + '">React</button>' +
            (own ? '<button type="button" data-edit="' + m.id + '">Edit</button>' : '') +
            (own ? '<button type="button" data-del="' + m.id + '">Delete</button>' : '') +
            (canPin ? '<button type="button" data-pin="' + m.id + '">' + (m.pinned ? 'Unpin' : 'Pin') + '</button>' : '') +
            '<button type="button" data-save="' + m.id + '">' + (m.bookmarked ? 'Saved' : 'Save') + '</button>' +
            '<button type="button" data-task="' + m.id + '">Create Task</button>' +
            '</div>'
        );
        wrap.innerHTML = avatar + '<div style="flex:1;min-width:0">' +
            '<div class="slack-msg__meta"><strong>' + esc(m.is_bot ? 'Invent' : m.name) + '</strong>' + esc(m.created_label || '') + edited + '</div>' +
            fwd + '<div class="slack-msg__body">' + (m.html || esc(m.body || '')) + '</div>' + file + task +
            (rx ? '<div class="mt-1">' + rx + '</div>' : '') + replies + seen +
            '</div>' + actions;
        bindMessageActions(wrap, m);
        return wrap;
    }

    function bindMessageActions(wrap, m) {
        const fwd = wrap.querySelector('[data-fwd]');
        if (fwd) fwd.addEventListener('click', function () { openForward(m.id); });
        const reply = wrap.querySelector('[data-reply]');
        if (reply) reply.addEventListener('click', function () { setReply(m); });
        const copy = wrap.querySelector('[data-copy]');
        if (copy) copy.addEventListener('click', function () {
            navigator.clipboard && navigator.clipboard.writeText(m.body || m.permalink || '');
        });
        const edit = wrap.querySelector('[data-edit]');
        if (edit) edit.addEventListener('click', async function () {
            const next = prompt('Edit message', m.body || '');
            if (next == null) return;
            const data = await api('/chat/messages/' + m.id, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ body: next })
            });
            (data.messages || []).forEach(function (row) { messageCard(row); });
        });
        const del = wrap.querySelector('[data-del]');
        if (del) del.addEventListener('click', async function () {
            if (!confirm('Delete this message?')) return;
            await api('/chat/messages/' + m.id, { method: 'DELETE' });
            const body = wrap.querySelector('.slack-msg__body');
            if (body) body.innerHTML = '<em>This message was deleted.</em>';
        });
        const pin = wrap.querySelector('[data-pin]');
        if (pin) pin.addEventListener('click', async function () {
            await api('/chat/messages/' + m.id + '/pin', { method: 'POST' });
            openChannel(activeId);
        });
        const save = wrap.querySelector('[data-save]');
        if (save) save.addEventListener('click', async function () {
            await api('/chat/messages/' + m.id + '/bookmark', { method: 'POST' });
            save.textContent = save.textContent === 'Saved' ? 'Save' : 'Saved';
        });
        const task = wrap.querySelector('[data-task]');
        if (task) task.addEventListener('click', function () { openTaskFrom(m); });
        const thread = wrap.querySelector('[data-thread]');
        if (thread) thread.addEventListener('click', function () { openThread(m.id); });
        wrap.addEventListener('click', function (e) {
            if (!isMobileChat()) return;
            if (e.target.closest('.slack-msg__act') || e.target.closest('a') || e.target.closest('button')) return;
            wrap.classList.toggle('is-open');
        });
        wrap.querySelectorAll('[data-react]').forEach(function (btn) {
            btn.addEventListener('click', function () { reactTo(m.id, btn.getAttribute('data-react')); });
        });
        const reactBtn = wrap.querySelector('[data-reactbtn]');
        if (reactBtn) reactBtn.addEventListener('click', function () {
            const emoji = prompt('Reaction emoji', '👍');
            if (emoji) reactTo(m.id, emoji);
        });
        const retry = wrap.querySelector('[data-retry]');
        if (retry) retry.addEventListener('click', function () { retryClient(m.client_id || String(m.id)); });
    }

    async function reactTo(id, emoji) {
        const data = await api('/chat/messages/' + id + '/react', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ emoji: emoji })
        });
        (data.messages || []).forEach(function (row) { messageCard(row); });
    }

    function appendMessages(rows, replace, fromPoll, targetFeed) {
        if (replace && !targetFeed) {
            feed.innerHTML = '';
            firstUnreadId = 0;
            oldestId = 0;
        }
        if (!rows.length && replace && !targetFeed) {
            feed.innerHTML = '<div class="slack-empty">This is the start of the conversation.</div>';
            return;
        }
        const dest = targetFeed || feed;
        const atBottom = dest.scrollHeight - dest.scrollTop - dest.clientHeight < 80;
        let incoming = false;
        rows.forEach(function (m) {
            if (fromPoll && !m.is_bot && Number(m.user_id) !== meId) incoming = true;
            if (!targetFeed && firstUnreadId && Number(m.id) === Number(firstUnreadId) && !document.querySelector('.slack-new')) {
                const div = document.createElement('div');
                div.className = 'slack-new';
                div.textContent = 'New messages';
                dest.appendChild(div);
            }
            messageCard(m, dest);
        });
        if (replace || atBottom) dest.scrollTop = dest.scrollHeight;
        if (incoming && !initialLoad) playTone();
    }

    function setHead(ch, detail) {
        const name = ch ? ((ch.type === 'public' || ch.type === 'private') ? '#' + ch.name : ch.name) : 'Slack';
        document.getElementById('slackRoomName').textContent = name;
        let sub = 'Message';
        if (detail && detail.last_seen_label) sub = detail.last_seen_label;
        else if (ch && ch.last_seen_label) sub = ch.last_seen_label;
        else if (ch && ch.type === 'group') sub = (ch.member_count || 0) + ' members';
        else if (ch && ch.type === 'bot') sub = 'Create a task, or check overdue, DAR, and SI';
        document.getElementById('slackRoomSub').textContent = sub;
        bodyEl.placeholder = ch ? 'Message ' + name : 'Message';
    }

    function setReply(m) {
        replyTo = m;
        const bar = document.getElementById('slackReplyBar');
        bar.classList.add('is-on');
        bar.innerHTML = 'Replying to ' + esc(m.name || '') + ': ' + esc((m.body || '').slice(0, 80)) +
            ' <button type="button" id="slackReplyJump">Jump</button> <button type="button" id="slackReplyClear">Cancel</button>';
        document.getElementById('slackReplyJump').addEventListener('click', function () {
            const el = document.getElementById('slack-msg-' + m.id);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
        document.getElementById('slackReplyClear').addEventListener('click', function () {
            replyTo = null;
            bar.classList.remove('is-on');
            bar.innerHTML = '';
        });
        bodyEl.focus();
    }

    async function openThread(parentId) {
        const pane = document.getElementById('slackThread');
        pane.classList.add('is-open');
        document.getElementById('slackApp').classList.add('is-thread');
        if (isMobileChat()) history.pushState({ chat: 'thread', id: parentId }, '', location.href);
        const data = await api('/chat/channels/' + activeId + '/messages?parent_id=' + parentId);
        const tf = document.getElementById('slackThreadFeed');
        tf.innerHTML = '';
        appendMessages(data.messages || [], false, false, tf);
        replyTo = { id: parentId, name: 'thread', body: '' };
        document.getElementById('slackReplyBar').classList.add('is-on');
        document.getElementById('slackReplyBar').innerHTML = 'Replying in thread <button type="button" id="slackReplyClear">Cancel</button>';
        const clr = document.getElementById('slackReplyClear');
        if (clr) clr.addEventListener('click', function () {
            replyTo = null;
            document.getElementById('slackReplyBar').classList.remove('is-on');
        });
    }

    function renderPins(rows) {
        const el = document.getElementById('slackPins');
        if (!rows || !rows.length) {
            el.classList.remove('is-on');
            el.innerHTML = '';
            return;
        }
        el.classList.add('is-on');
        el.innerHTML = '<strong>Pinned</strong> ' + rows.map(function (m) {
            return '<button type="button" data-pinjump="' + m.id + '">' + esc((m.body || m.attachment_name || 'Message').slice(0, 60)) + '</button>';
        }).join(' · ');
        el.querySelectorAll('[data-pinjump]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const node = document.getElementById('slack-msg-' + btn.getAttribute('data-pinjump'));
                if (node) node.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    }

    async function openChannel(id, aroundId) {
        activeId = id;
        lastId = 0;
        oldestId = 0;
        const ch = channels.find(function (c) { return c.id === id; }) || {};
        firstUnreadId = ch.unread > 0 ? (ch.first_unread_id || 0) : 0;
        composer.hidden = false;
        renderNav();
        setHead(ch);
        const draft = localStorage.getItem(DRAFT_KEY + id);
        if (draft && !bodyEl.value) bodyEl.value = draft;
        const q = aroundId ? ('?around=' + aroundId) : '';
        const data = await api('/chat/channels/' + id + '/messages' + q);
        if (data.channel) setHead(ch, data.channel);
        appendMessages(data.messages || [], true, false);
        applyReceipts(data.receipts || {});
        renderPins(data.pinned || []);
        showTyping(data.typing || []);
        document.getElementById('slackApp').classList.add('is-room');
        document.getElementById('slackApp').classList.remove('is-thread');
        history.pushState({ chat: 'room', id: id }, '', '/chat?channel=' + id + (aroundId ? '&message=' + aroundId : ''));
        knownInbox[id] = ch.last_id || lastId;
        broadcastChat({ type: 'open', channelId: id });
        if (aroundId) {
            const node = document.getElementById('slack-msg-' + aroundId);
            if (node) node.scrollIntoView({ block: 'center' });
        }
    }

    function noticeInbox(next) {
        if (initialLoad) {
            next.forEach(function (ch) { knownInbox[ch.id] = ch.last_id || 0; });
            return;
        }
        let ping = false;
        next.forEach(function (ch) {
            const prev = knownInbox[ch.id] || 0;
            if (ch.last_id > prev && ch.id !== activeId && ch.unread > 0) ping = true;
            knownInbox[ch.id] = ch.last_id || prev;
        });
        if (ping) playTone();
    }

    function showTyping(rows) {
        const el = document.getElementById('slackTyping');
        if (!el) return;
        if (!rows || !rows.length) { el.textContent = ''; return; }
        el.textContent = rows.map(function (r) { return r.name; }).join(', ') + (rows.length === 1 ? ' is' : ' are') + ' typing…';
    }

    function showAlerts(alerts) {
        if (!alerts || !alerts.length || typeof Notification === 'undefined') return;
        if (Notification.permission === 'default') Notification.requestPermission();
        if (Notification.permission !== 'granted') return;
        alerts.forEach(function (a) {
            if (Number(a.channel_id) === activeId && document.hasFocus()) return;
            try {
                const n = new Notification(a.title || 'Chat', { body: a.body || '', tag: 'chat-' + a.message_id });
                n.onclick = function () {
                    window.focus();
                    if (a.channel_id) openChannel(a.channel_id, a.message_id);
                };
            } catch (e) {}
        });
    }

    async function tick() {
        if (sending) return;
        if (!navigator.onLine) {
            setConn('off', 'Offline');
            return;
        }
        tickN += 1;
        if (syncAbort) syncAbort.abort();
        syncAbort = new AbortController();
        try {
            const inbox = (tickN % 4 === 0) ? '&inbox=1' : '';
            const data = await api('/chat/sync?channel=' + (activeId || 0) + '&after=' + lastId + inbox, { signal: syncAbort.signal });
            if (connState !== 'online') {
                setConn('online', 'Connected');
                fetch('/chat/health-event', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'reconnect', channel_id: activeId || null })
                });
                flushOutbox();
            }
            if (data.channels) {
                noticeInbox(data.channels);
                channels = data.channels;
                renderNav();
                const ch = channels.find(function (c) { return c.id === activeId; });
                if (ch) setHead(ch, data.channel);
            }
            if (data.messages && data.messages.length) appendMessages(data.messages, false, true);
            applyReceipts(data.receipts || {});
            if (typeof data.unread === 'number') updateTopbar(data.unread);
            showTyping(data.typing || []);
            showAlerts(data.alerts || []);
        } catch (e) {
            if (e && e.name === 'AbortError') return;
            setConn('wait', 'Reconnecting...');
        }
        initialLoad = false;
    }

    function appendOptimistic(tempId, text, failed) {
        const empty = feed.querySelector('.slack-empty');
        if (empty) empty.remove();
        const now = new Date();
        const label = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        appendMessages([{
            id: tempId,
            client_id: tempId,
            user_id: meId,
            is_bot: false,
            name: meName,
            avatar: meAvatar,
            body: text,
            html: esc(text).replace(/\n/g, '<br>'),
            created_label: label,
            seen_label: failed ? 'Failed · Retry' : 'Sending…',
            seen: false,
            pending: !failed,
            failed: !!failed
        }], false, false);
        const el = document.getElementById('slack-msg-' + tempId);
        if (el && failed) {
            const seen = el.querySelector('.slack-msg__seen');
            if (seen) {
                seen.innerHTML = 'Failed <button type="button" data-retry="' + tempId + '">Retry</button>';
                bindMessageActions(el, { id: tempId, client_id: tempId, body: text, user_id: meId });
            }
        }
    }

    function setUploadProgress(pct) {
        const bar = document.getElementById('slackProgress');
        const fill = bar ? bar.querySelector('span') : null;
        if (!bar || !fill) return;
        if (pct == null) {
            bar.classList.remove('is-on');
            fill.style.width = '0%';
            return;
        }
        bar.classList.add('is-on');
        fill.style.width = Math.max(0, Math.min(100, pct)) + '%';
    }

    function postMessage(channelId, text, file, cid, parentId) {
        const fd = new FormData();
        fd.append('body', text);
        fd.append('client_id', cid);
        if (parentId) fd.append('parent_id', parentId);
        if (file) fd.append('file', file);
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/chat/channels/' + channelId + '/messages');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            if (file && xhr.upload) {
                xhr.upload.onprogress = function (e) {
                    if (e.lengthComputable) setUploadProgress(Math.round((e.loaded / e.total) * 100));
                };
            }
            xhr.onload = function () {
                setUploadProgress(null);
                let data = {};
                try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
                if (xhr.status >= 200 && xhr.status < 300) resolve(data);
                else reject(new Error(data.message || 'Request failed'));
            };
            xhr.onerror = function () {
                setUploadProgress(null);
                reject(new Error('Network error'));
            };
            xhr.send(fd);
        });
    }

    function markFailed(cid, text, channelId, file, parentId) {
        const box = readOutbox();
        if (!box.find(function (r) { return r.client_id === cid; })) {
            box.push({ client_id: cid, channel_id: channelId, body: text, parent_id: parentId || null, created_at: Date.now(), has_file: !!file });
            writeOutbox(box);
        }
        if (file) pendingFiles[cid] = file;
        const pending = document.getElementById('slack-msg-' + cid);
        if (pending) {
            pending.classList.remove('is-pending');
            pending.classList.add('is-failed');
            const seen = pending.querySelector('.slack-msg__seen');
            if (seen) {
                seen.innerHTML = 'Failed <button type="button" data-retry="' + cid + '">Retry</button>';
                seen.querySelector('[data-retry]').addEventListener('click', function () { retryClient(cid); });
            }
        }
        fetch('/chat/health-event', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'failed_delivery', channel_id: channelId })
        });
    }

    async function retryClient(cid) {
        const row = readOutbox().find(function (r) { return r.client_id === cid; }) || { client_id: cid, channel_id: activeId, body: '' };
        const el = document.getElementById('slack-msg-' + cid);
        const text = row.body || (el && el.querySelector('.slack-msg__body') ? el.querySelector('.slack-msg__body').textContent : '');
        try {
            const data = await postMessage(row.channel_id || activeId, text, pendingFiles[cid] || null, cid, row.parent_id);
            writeOutbox(readOutbox().filter(function (r) { return r.client_id !== cid; }));
            if (el) el.remove();
            appendMessages(data.messages || [], false, false);
        } catch (e) {
            markFailed(cid, text, row.channel_id || activeId, pendingFiles[cid], row.parent_id);
        }
    }

    async function flushOutbox() {
        const rows = readOutbox();
        for (let i = 0; i < rows.length; i++) {
            await retryClient(rows[i].client_id);
        }
    }

    composer.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!activeId || sending) return;
        const text = bodyEl.value;
        const file = fileEl.files[0] || null;
        if (!text.trim() && !file) return;
        if (syncAbort) syncAbort.abort();
        const cid = clientId();
        const parentId = replyTo ? replyTo.id : null;
        appendOptimistic(cid, text);
        bodyEl.value = '';
        localStorage.removeItem(DRAFT_KEY + activeId);
        fileEl.value = '';
        fileNameEl.textContent = '';
        sending = true;
        if (!navigator.onLine) {
            markFailed(cid, text, activeId, file, parentId);
            sending = false;
            return;
        }
        try {
            const data = await postMessage(activeId, text, file, cid, parentId);
            const pending = document.getElementById('slack-msg-' + cid);
            if (pending) pending.remove();
            appendMessages(data.messages || [], false, false);
            writeOutbox(readOutbox().filter(function (r) { return r.client_id !== cid; }));
            broadcastChat({ type: 'messages', channelId: activeId, messages: data.messages || [] });
        } catch (err) {
            markFailed(cid, text, activeId, file, parentId);
        } finally {
            sending = false;
        }
    });

    bodyEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            composer.requestSubmit();
        }
    });
    document.getElementById('slackAttachBtn').addEventListener('click', function () { fileEl.click(); });
    const cameraEl = document.getElementById('slackCamera');
    document.getElementById('slackCameraBtn').addEventListener('click', function () { cameraEl.click(); });
    function takeFile(input) {
        if (!input.files[0]) return;
        const dt = new DataTransfer();
        dt.items.add(input.files[0]);
        fileEl.files = dt.files;
        fileNameEl.textContent = input.files[0].name;
    }
    fileEl.addEventListener('change', function () {
        fileNameEl.textContent = fileEl.files[0] ? fileEl.files[0].name : '';
    });
    cameraEl.addEventListener('change', function () { takeFile(cameraEl); });

    function renderPeople(q) {
        const query = String(q || '').toLowerCase().trim();
        const rows = directory.filter(function (u) {
            if (!query) return false;
            return (u.name || '').toLowerCase().indexOf(query) >= 0 || (u.email || '').toLowerCase().indexOf(query) >= 0;
        }).slice(0, 12);
        peopleList.innerHTML = rows.map(function (u) {
            return '<button type="button" data-user="' + u.id + '"><img src="' + esc(u.avatar || '') + '" alt=""><span>' + esc(u.name) + (u.online ? ' · Active' : '') + '</span></button>';
        }).join('');
        peopleList.classList.toggle('is-open', rows.length > 0);
        peopleList.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const data = await api('/chat/dms', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: Number(btn.dataset.user) })
                });
                searchEl.value = '';
                peopleList.classList.remove('is-open');
                const inbox = await api('/chat/inbox');
                channels = inbox.channels || channels;
                if (inbox.directory) directory = inbox.directory;
                renderNav();
                openChannel(data.channel_id);
            });
        });
    }
    searchEl.addEventListener('input', function () { renderPeople(searchEl.value); });
    document.getElementById('slackNewDmBtn').addEventListener('click', function () {
        searchEl.focus();
        searchEl.placeholder = 'Type a name to message';
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.slack-nav__search')) peopleList.classList.remove('is-open');
    });

    const groupMembers = document.getElementById('slackGroupMembers');
    function fillDirectorySelect(sel) {
        if (!sel) return;
        sel.innerHTML = directory.map(function (u) {
            return '<option value="' + u.id + '">' + esc(u.name) + (u.online ? ' (Active now)' : '') + '</option>';
        }).join('');
    }
    document.getElementById('slackNewGroupBtn').addEventListener('click', function () {
        fillDirectorySelect(groupMembers);
        window.bootstrap && window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackGroupModal')).show();
    });
    document.getElementById('slackGroupForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        const data = await api('/chat/groups', { method: 'POST', body: fd });
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackGroupModal')).hide();
        e.target.reset();
        const inbox = await api('/chat/inbox');
        channels = inbox.channels || channels;
        renderNav();
        openChannel(data.channel_id);
    });

    const newCh = document.getElementById('slackNewChannelBtn');
    if (newCh) {
        newCh.addEventListener('click', function () {
            window.bootstrap && window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackChannelModal')).show();
        });
        document.getElementById('slackChannelForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const data = await api('/chat/channels', { method: 'POST', body: new FormData(e.target) });
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackChannelModal')).hide();
            e.target.reset();
            const inbox = await api('/chat/inbox');
            channels = inbox.channels || channels;
            renderNav();
            openChannel(data.channel_id);
        });
    }

    function openForward(id) {
        document.getElementById('slackForwardMessageId').value = id;
        fillForwardTargets();
        window.bootstrap && window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackForwardModal')).show();
    }
    document.getElementById('slackForwardForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const mid = document.getElementById('slackForwardMessageId').value;
        const dest = document.getElementById('slackForwardTarget').value;
        const data = await api('/chat/messages/' + mid + '/forward', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ channel_id: Number(dest) })
        });
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackForwardModal')).hide();
        await openChannel(data.channel_id);
    });

    bodyEl.addEventListener('input', function () {
        if (activeId) localStorage.setItem(DRAFT_KEY + activeId, bodyEl.value);
        const at = bodyEl.value.lastIndexOf('@');
        const pick = document.getElementById('slackMentionPick');
        if (at >= 0) {
            const q = bodyEl.value.slice(at + 1).split(/\s/)[0].toLowerCase();
            const extras = [];
            if (canAnnounce && 'channel'.indexOf(q) === 0) extras.push({ id: 0, name: 'channel' });
            if (canAnnounce && 'everyone'.indexOf(q) === 0) extras.push({ id: 0, name: 'everyone' });
            const rows = extras.concat(directory.filter(function (u) {
                return (u.name || '').toLowerCase().indexOf(q) >= 0;
            }).slice(0, 8));
            pick.innerHTML = rows.map(function (u) {
                return '<button type="button" data-mention="' + esc(u.name) + '">@' + esc(u.name) + '</button>';
            }).join('');
            pick.classList.toggle('is-open', rows.length > 0);
            pick.querySelectorAll('button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    bodyEl.value = bodyEl.value.slice(0, at) + '@' + btn.getAttribute('data-mention') + ' ';
                    pick.classList.remove('is-open');
                    bodyEl.focus();
                });
            });
        } else {
            pick.classList.remove('is-open');
        }
        if (activeId) {
            fetch('/chat/presence', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ typing_channel_id: activeId })
            });
        }
    });

    feed.addEventListener('scroll', async function () {
        if (loadingOlder || !activeId || feed.scrollTop > 40 || !oldestId) return;
        loadingOlder = true;
        try {
            const data = await api('/chat/channels/' + activeId + '/messages?before=' + oldestId);
            const prevH = feed.scrollHeight;
            (data.messages || []).reverse().forEach(function (m) {
                if (document.getElementById('slack-msg-' + m.id)) return;
                feed.insertBefore(buildMessageEl(m), feed.firstChild);
                const numId = Number(m.id);
                if (!Number.isNaN(numId) && numId > 0 && (!oldestId || numId < oldestId)) oldestId = numId;
            });
            feed.scrollTop = feed.scrollHeight - prevH;
        } catch (e) {}
        loadingOlder = false;
    });

    document.getElementById('slackMarkReadBtn').addEventListener('click', async function () {
        if (!activeId) return;
        const data = await api('/chat/channels/' + activeId + '/read', { method: 'POST' });
        if (typeof data.unread === 'number') updateTopbar(data.unread);
        const ch = channels.find(function (c) { return c.id === activeId; });
        if (ch) ch.unread = 0;
        renderNav();
    });
    document.getElementById('slackMarkAllBtn').addEventListener('click', async function () {
        await api('/chat/read-all', { method: 'POST' });
        channels.forEach(function (c) { c.unread = 0; });
        renderNav();
        updateTopbar(0);
    });
    document.getElementById('slackSearchOpen').addEventListener('click', function () {
        const sel = document.getElementById('slackSearchUser');
        sel.innerHTML = '<option value="">Any person</option>' + directory.map(function (u) {
            return '<option value="' + u.id + '">' + esc(u.name) + '</option>';
        }).join('');
        window.bootstrap && window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackSearchModal')).show();
    });
    document.getElementById('slackSearchForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        const qs = new URLSearchParams();
        fd.forEach(function (v, k) { if (v) qs.set(k, v); });
        const data = await api('/chat/search?' + qs.toString());
        const box = document.getElementById('slackSearchResults');
        box.innerHTML = (data.results || []).map(function (r) {
            return '<button type="button" class="list-group-item list-group-item-action" data-jump-ch="' + r.channel_id + '" data-jump-m="' + r.id + '">' +
                '<strong>' + esc(r.channel_name || '') + '</strong> · ' + esc(r.name) + '<div class="small">' + esc(r.preview) + '</div></button>';
        }).join('') || '<div class="text-muted">No matches</div>';
        box.querySelectorAll('[data-jump-ch]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackSearchModal')).hide();
                openChannel(Number(btn.getAttribute('data-jump-ch')), Number(btn.getAttribute('data-jump-m')));
            });
        });
    });
    document.getElementById('slackPrefsBtn').addEventListener('click', function () {
        window.bootstrap && window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackPrefsModal')).show();
    });
    document.getElementById('slackPrefsForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        await api('/chat/prefs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: document.getElementById('slackNotifyMode').value })
        });
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackPrefsModal')).hide();
    });
    document.getElementById('slackStatusSel').addEventListener('change', function () {
        fetch('/chat/presence', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: this.value })
        });
    });
    document.getElementById('slackThreadClose').addEventListener('click', function () {
        document.getElementById('slackThread').classList.remove('is-open');
        document.getElementById('slackApp').classList.remove('is-thread');
        replyTo = null;
        document.getElementById('slackReplyBar').classList.remove('is-on');
    });

    function showInbox() {
        document.getElementById('slackApp').classList.remove('is-room', 'is-thread');
        document.getElementById('slackThread').classList.remove('is-open');
        composer.hidden = true;
        activeId = 0;
        history.replaceState({ chat: 'inbox' }, '', '/chat');
    }
    document.getElementById('slackBackBtn').addEventListener('click', function () {
        if (document.getElementById('slackApp').classList.contains('is-thread')) {
            document.getElementById('slackThreadClose').click();
            return;
        }
        showInbox();
    });
    window.addEventListener('popstate', function () {
        if (!isMobileChat()) return;
        if (document.getElementById('slackApp').classList.contains('is-thread')) {
            document.getElementById('slackThreadClose').click();
            return;
        }
        if (document.getElementById('slackApp').classList.contains('is-room')) {
            showInbox();
        }
    });
    if (tabBus) {
        tabBus.onmessage = function (ev) {
            const d = ev.data || {};
            if (d.type === 'messages' && d.channelId === activeId && d.messages) {
                appendMessages(d.messages, false, false);
            }
            if (d.type === 'unread' && typeof d.unread === 'number') updateTopbar(d.unread);
        };
    }
    function revealInstall() {
        const btn = document.getElementById('slackInstallBtn');
        if (btn && window.__inventPwaInstall && !window.matchMedia('(display-mode: standalone)').matches) {
            btn.classList.add('is-on');
        }
    }
    window.addEventListener('invent-pwa-install-ready', revealInstall);
    revealInstall();
    document.getElementById('slackInstallBtn').addEventListener('click', function () {
        if (typeof installPWA === 'function') {
            installPWA().then(function () {
                document.getElementById('slackInstallBtn').classList.remove('is-on');
            });
        }
    });

    function openTaskFrom(m) {
        document.getElementById('slackTaskMessageId').value = m.id;
        document.getElementById('slackTaskPreview').textContent = m.body || '';
        const sel = document.getElementById('slackTaskAssignee');
        sel.innerHTML = '<option value="' + meId + '">' + esc(meName) + '</option>' + directory.map(function (u) {
            return '<option value="' + u.id + '">' + esc(u.name) + '</option>';
        }).join('');
        window.bootstrap && window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackTaskModal')).show();
    }
    document.getElementById('slackTaskForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const mid = document.getElementById('slackTaskMessageId').value;
        const data = await api('/chat/messages/' + mid + '/task', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                assign_user_id: Number(document.getElementById('slackTaskAssignee').value || meId),
                due_date: document.getElementById('slackTaskDue').value || null
            })
        });
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('slackTaskModal')).hide();
        if (data.task_url) window.open(data.task_url, '_blank');
    });

    window.addEventListener('offline', function () { setConn('off', 'Offline'); });
    window.addEventListener('online', function () {
        setConn('wait', 'Reconnecting...');
        flushOutbox();
        tick();
    });
    window.addEventListener('beforeunload', function () {
        navigator.sendBeacon && navigator.sendBeacon('/chat/presence', new Blob([JSON.stringify({ typing_channel_id: 0 })], { type: 'application/json' }));
    });

    api('/chat/inbox').then(function (data) {
        channels = data.channels || [];
        directory = data.directory || [];
        renderNav();
        const preferred = channels.find(function (c) { return c.id === startChannel; })
            || channels.find(function (c) { return c.unread > 0; })
            || channels.find(function (c) { return c.type === 'bot'; })
            || channels[0];
        if (preferred && (startChannel || startMessage || !isMobileChat())) {
            return openChannel(preferred.id, startMessage || 0);
        }
    }).catch(function (err) {
        feed.innerHTML = '<div class="slack-empty">' + esc(err.message) + '</div>';
    }).finally(function () {
        setInterval(tick, 800);
        setInterval(function () {
            fetch('/chat/presence', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: document.getElementById('slackStatusSel').value })
            });
        }, 15000);
        if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        flushOutbox();
    });
})();
</script>
@endsection
