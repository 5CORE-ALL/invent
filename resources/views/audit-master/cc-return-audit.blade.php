{{-- CC Return Audit uses the exact same screen as CC Messages Audit (including the
     "Add" button that starts an audit for a chosen channel). The controller passes
     $auditModule = 'cc_return' and the page title/icon, so nothing else is needed here. --}}
@extends('audit-master.cc-messages-audit')
