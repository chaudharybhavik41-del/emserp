@php
    $isRoute = fn ($patterns) => request()->routeIs($patterns);

    /**
     * Safe route() wrapper: prevents sidebar crash when a route name doesn't exist.
     * Returns '#' if route missing.
     */
    $safeRoute = function (string $name, $params = []) {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name, $params) : '#';
    };

    /**
     * Pick the first existing route name from a list of candidates.
     * This prevents sidebar break if route naming differs across environments.
     */
    $pickRoute = function (array $candidates) {
        foreach ($candidates as $name) {
            if (\Illuminate\Support\Facades\Route::has($name)) {
                return $name;
            }
        }
        return null;
    };

    /**
     * Project context (only exists on routes like /projects/{project}/...)
     */
    $currentProject = $project ?? request()->route('project');

    /**
     * IMPORTANT: This sidebar partial is included TWICE (desktop + mobile offcanvas).
     * Bootstrap collapse uses document-level selectors, so duplicate IDs cause 'blank' expansions.
     * We prefix each collapse ID with the sidebar instance id to make them unique.
     */
    $sidebarId = $sidebarId ?? 'desktop';
    $sidebarKey = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $sidebarId) ?: 'sidebar';
    $secId = fn (string $key) => 'erp-sb-' . $sidebarKey . '-' . $key;

    // Which section should be expanded by default based on current route
    $openCore       = $isRoute(['access.*','users.*','uoms.*','departments.*','companies.*','settings.*','mail-profiles.*','mail-templates.*','standard-terms.*','notifications.*']);
    $openSecurity   = $isRoute(['activity-logs.*','login-logs.*','sessions.*']);
    $openMasters    = $isRoute(['material-types.*','material-categories.*','material-subcategories.*','items.*','parties.*']);
    $openCrm        = $isRoute(['crm.*']);
    $openHr         = request()->is('hr*') || $isRoute(['hr.*']);
    $openPurchase   = $isRoute(['purchase-indents.*','purchase-rfqs.*','purchase-orders.*','purchase.bills.*']);
    $openStore      = $isRoute(['store.*','material-receipts.*','store-stock.*','store-stock-items.*','store-stock-summary.*','store-stock-register.*','store-low-stock.*','store-remnants.*','store-requisitions.*','store-issues.*','store-returns.*','store-stock-adjustments.*','gate-passes.*','machines.*','machine-assignments.*','maintenance.plans.*','maintenance.logs.*','maintenance.breakdowns.*','machine-calibrations.*','maintenance.reports.*']);
    $openAccounting = $isRoute(['accounting.*','reports.*','payments.*','receipts.*']);
    $openProduction = $isRoute(['production.*']);
    $openProjects   = $isRoute(['projects.*','bom-templates.*']);
    $openTasks      = $isRoute(['tasks.*','task-board.*','task-lists.*','task-settings.*']);
	$openSupport    = $isRoute(['support.*']);
	$openStorage    = $isRoute(['storage.*']);

    // Core section is not wrapped in @canany in the original file, so avoid showing an empty collapsible.
    $showCore = false;
    if (auth()->check()) {
        $showCore = auth()->user()->canany([
            'core.access.manage',
            'core.user.view',
            'core.uom.view',
            'core.department.view',
            'core.company.view',
            'core.system_setting.view',
            'core.system_setting.update',
            'core.mail_profile.view',
            'core.mail_template.view',
            'standard-terms.view',
        ]);
    }
    $showCore = $showCore || \Illuminate\Support\Facades\Route::has('notifications.index');
@endphp

<nav class="border-end bg-white d-flex flex-column erp-sidebar" id="erp-sidebar-{{ $sidebarKey }}">
    

    <div class="flex-grow-1 overflow-auto erp-sidebar-nav">
        <div class="p-2">

            {{-- Sticky search (optional but recommended) --}}
            <div class="erp-sidebar-search-wrap mb-2 rounded-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-body">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" class="form-control erp-sidebar-search" placeholder="Search menu..." aria-label="Search menu">
                    <button class="btn btn-outline-secondary erp-sidebar-search-clear" type="button" aria-label="Clear search">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            {{-- Dashboard (always visible) --}}
            <div class="mb-2">
                <a href="{{ $safeRoute('dashboard') }}"
                   class="btn btn-sm w-100 text-start d-flex align-items-center gap-2 erp-transition
                          {{ $isRoute('dashboard') ? 'btn-primary text-white' : 'btn-outline-light text-body-secondary border-0' }}">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="small text-uppercase text-muted px-1 mb-1">
                Navigation
            </div>

            <ul class="nav flex-column small">

               @if(\Illuminate\Support\Facades\Route::has('reports-hub.index'))
			<li class="nav-item erp-sidebar-section mb-1">
  			  <a data-erp-menu-item href="{{ $safeRoute('reports-hub.index') }}"
    		   class="nav-link erp-nav-link d-flex align-items-center px-3 py-2
              {{ $isRoute('reports-hub.*') ? 'active' : 'text-body-secondary' }}">
    	    <i class="bi bi-bar-chart-line me-2"></i>
  	      <span>Reports Hub</span>
  		  </a>
			</li>
			@endif 
              
              @if($showCore)
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('core') }}"
                            aria-expanded="{{ $openCore ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('core') }}"
                            data-erp-section-toggle
                            data-erp-section-key="core">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-gear"></i>
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Core &amp; Settings</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>
                    <div id="{{ $secId('core') }}" class="collapse {{ $openCore ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                @can('core.access.manage')
                    <li class="nav-item">
                        <a data-erp-menu-item href="{{ $safeRoute('access.roles.index') }}"
                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                  {{ $isRoute('access.*') ? 'active' : 'text-body-secondary' }}">
                            <i class="bi bi-shield-lock me-2"></i>
                            <span>Access Control</span>
                        </a>
                    </li>
                @endcan

                @can('core.user.view')
                    <li class="nav-item">
                        <a data-erp-menu-item href="{{ $safeRoute('users.index') }}"
                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                  {{ $isRoute('users.*') ? 'active' : 'text-body-secondary' }}">
                            <i class="bi bi-people me-2"></i>
                            <span>Users</span>
                        </a>
                    </li>
                @endcan
				
                @can('core.access.manage')
   			 <li class="nav-item">
        <a data-erp-menu-item
           href="{{ $safeRoute('access.storage-access.index') }}"
           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                  {{ $isRoute('access.storage-access.*') ? 'active' : 'text-body-secondary' }}">
            <i class="bi bi-person-check me-2"></i>
   	         <span>Storage Access</span>
   		     </a>
  			  </li>
			@endcan
               
                          
                 @can('core.uom.view')
                    <li class="nav-item">
                        <a data-erp-menu-item href="{{ $safeRoute('uoms.index') }}"
                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                  {{ $isRoute('uoms.*') ? 'active' : 'text-body-secondary' }}">
                            <i class="bi bi-rulers me-2"></i>
                            <span>UOMs</span>
                        </a>
                    </li>
                @endcan

                @can('core.department.view')
                    <li class="nav-item">
                        <a data-erp-menu-item href="{{ $safeRoute('departments.index') }}"
                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                  {{ $isRoute('departments.*') ? 'active' : 'text-body-secondary' }}">
                            <i class="bi bi-diagram-3 me-2"></i>
                            <span>Departments</span>
                        </a>
                    </li>
                @endcan

                @can('core.company.view')
                    <li class="nav-item">
                        <a data-erp-menu-item href="{{ $safeRoute('companies.index') }}"
                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                  {{ $isRoute('companies.*') ? 'active' : 'text-body-secondary' }}">
                            <i class="bi bi-buildings me-2"></i>
                            <span>Companies</span>
                        </a>
                    </li>
                @endcan

                @can('core.system_setting.view')
                    @if(\Illuminate\Support\Facades\Route::has('settings.general'))
                        <li class="nav-item">
                            <a data-erp-menu-item href="{{ route('settings.general') }}"
                               class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                      {{ $isRoute('settings.general') ? 'active' : 'text-body-secondary' }}">
                                <i class="bi bi-sliders me-2"></i>
                                <span>General Settings</span>
                            </a>
                        </li>
                    @endif
                @endcan

                @can('core.system_setting.update')
                    @if(\Illuminate\Support\Facades\Route::has('settings.security'))
                        <li class="nav-item">
                            <a data-erp-menu-item href="{{ route('settings.security') }}"
                               class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                      {{ $isRoute('settings.security') ? 'active' : 'text-body-secondary' }}">
                                <i class="bi bi-shield-check me-2"></i>
                                <span>Security Settings</span>
                            </a>
                        </li>
                    @endif
                @endcan

                @can('core.mail_profile.view')
                    @if(\Illuminate\Support\Facades\Route::has('mail-profiles.index'))
                        <li class="nav-item">
                            <a data-erp-menu-item href="{{ route('mail-profiles.index') }}"
                               class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                      {{ $isRoute('mail-profiles.*') ? 'active' : 'text-body-secondary' }}">
                                <i class="bi bi-envelope-gear me-2"></i>
                                <span>Mail Profiles</span>
                            </a>
                        </li>
                    @endif
                @endcan

                @can('core.mail_template.view')
                    @if(\Illuminate\Support\Facades\Route::has('mail-templates.index'))
                        <li class="nav-item">
                            <a data-erp-menu-item href="{{ route('mail-templates.index') }}"
                               class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                      {{ $isRoute('mail-templates.*') ? 'active' : 'text-body-secondary' }}">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                <span>Mail Templates</span>
                            </a>
                        </li>
                    @endif
                @endcan

              	@can('standard-terms.view')
   				 @if(\Illuminate\Support\Facades\Route::has('standard-terms.index'))
        			<li class="nav-item">
      			      <a data-erp-menu-item href="{{ route('standard-terms.index') }}"
     		          class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                      {{ $isRoute('standard-terms.*') ? 'active' : 'text-body-secondary' }}">
   		             <i class="bi bi-file-earmark-text me-2"></i>
   		             <span>Standard Terms</span>
 			           </a>
			        </li>
 				   @endif
					@endcan              
                @if(\Illuminate\Support\Facades\Route::has('notifications.index'))
                    <li class="nav-item">
                        <a data-erp-menu-item href="{{ route('notifications.index') }}"
                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                  {{ $isRoute('notifications.*') ? 'active' : 'text-body-secondary' }}">
                            <i class="bi bi-bell me-2"></i>
                            <span>Notifications</span>
                        </a>
                    </li>
                @endif

                        </ul>
                    </div>
                </li>
                @endif
                @canany(['core.activity_log.view', 'core.login_log.view'])
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('security') }}"
                            aria-expanded="{{ $openSecurity ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('security') }}"
                            data-erp-section-toggle
                            data-erp-section-key="security">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-shield-lock"></i>
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Security &amp; Audit</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>
                    <div id="{{ $secId('security') }}" class="collapse {{ $openSecurity ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                    @can('core.activity_log.view')
                        @if(\Illuminate\Support\Facades\Route::has('activity-logs.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('activity-logs.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('activity-logs.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-journal-text me-2"></i>
                                    <span>Activity Logs</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('core.login_log.view')
                        @if(\Illuminate\Support\Facades\Route::has('login-logs.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('login-logs.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('login-logs.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-door-open me-2"></i>
                                    <span>Login Logs</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @if(\Illuminate\Support\Facades\Route::has('sessions.index'))
                        <li class="nav-item">
                            <a data-erp-menu-item href="{{ route('sessions.index') }}"
                               class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                      {{ $isRoute('sessions.*') ? 'active' : 'text-body-secondary' }}">
                                <i class="bi bi-laptop me-2"></i>
                                <span>My Sessions</span>
                            </a>
                        </li>
                    @endif
                        </ul>
                    </div>
                </li>
                @endcanany              
              	
              	{{-- ============================================== --}}
                {{-- SUPPORT CENTER (Accordion Style)               --}}
                {{-- ============================================== --}}
                @canany(['support.document.view', 'support.digest.view'])
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('support') }}"
                            aria-expanded="{{ $openSupport ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('support') }}"
                            data-erp-section-toggle
                            data-erp-section-key="support">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-life-preserver"></i> {{-- Main Section Icon --}}
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Support</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>

                    <div id="{{ $secId('support') }}" class="collapse {{ $openSupport ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                            
                            @can('support.document.view')
                                @if(\Illuminate\Support\Facades\Route::has('support.documents.index'))
                                    <li class="nav-item">
                                        <a data-erp-menu-item href="{{ route('support.documents.index') }}"
                                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                                  {{ $isRoute('support.documents.*') ? 'active' : 'text-body-secondary' }}">
                                            <i class="bi bi-folder2-open me-2"></i>
                                            <span>Document Library</span>
                                        </a>
                                    </li>
                                @endif
                            @endcan

                            @can('support.digest.view')
                                @if(\Illuminate\Support\Facades\Route::has('support.digest.preview'))
                                    <li class="nav-item">
                                        <a data-erp-menu-item href="{{ route('support.digest.preview') }}"
                                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                                  {{ $isRoute('support.digest.*') ? 'active' : 'text-body-secondary' }}">
                                            <i class="bi bi-envelope-paper me-2"></i>
                                            <span>Daily Digest</span>
                                        </a>
                                    </li>
                                @endif
                            @endcan

                        </ul>
                    </div>
                </li>
                @endcanany
              
                @canany(['core.material_type.view', 'core.material_category.view', 'core.item.view', 'core.party.view'])
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('masters') }}"
                            aria-expanded="{{ $openMasters ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('masters') }}"
                            data-erp-section-toggle
                            data-erp-section-key="masters">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-box-seam"></i>
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Material Masters</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>
                    <div id="{{ $secId('masters') }}" class="collapse {{ $openMasters ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                    @can('core.material_type.view')
                        @if(\Illuminate\Support\Facades\Route::has('material-types.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('material-types.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('material-types.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-collection me-2"></i>
                                    <span>Material Types</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('core.material_category.view')
                        @if(\Illuminate\Support\Facades\Route::has('material-categories.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('material-categories.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('material-categories.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-grid me-2"></i>
                                    <span>Categories</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('core.material_subcategory.view')
                        @if(\Illuminate\Support\Facades\Route::has('material-subcategories.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('material-subcategories.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('material-subcategories.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-grid-3x3-gap me-2"></i>
                                    <span>Subcategories</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('core.item.view')
                        @if(\Illuminate\Support\Facades\Route::has('items.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('items.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('items.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-box me-2"></i>
                                    <span>Items</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('core.party.view')
                        @if(\Illuminate\Support\Facades\Route::has('parties.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('parties.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('parties.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-person-rolodex me-2"></i>
                                    <span>Parties</span>
                                </a>
                            </li>
                        @endif
                    @endcan
                        </ul>
                    </div>
                </li>
                @endcanany
                @canany(['crm.lead.view', 'crm.quotation.view'])
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('crm') }}"
                            aria-expanded="{{ $openCrm ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('crm') }}"
                            data-erp-section-toggle
                            data-erp-section-key="crm">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-lines-fill"></i>
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">CRM</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>
                    <div id="{{ $secId('crm') }}" class="collapse {{ $openCrm ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                    @can('crm.lead.view')
                        @if(\Illuminate\Support\Facades\Route::has('crm.leads.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('crm.leads.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('crm.leads.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-funnel me-2"></i>
                                    <span>Leads</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('crm.quotation.view')
                        @if(\Illuminate\Support\Facades\Route::has('crm.quotations.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('crm.quotations.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('crm.quotations.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    <span>Quotations</span>
                                </a>
                            </li>
                        @endif
                    @endcan
                          @can('crm.quotation.breakup_templates.manage')
                        @if(\Illuminate\Support\Facades\Route::has('crm.quotation-breakup-templates.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('crm.quotation-breakup-templates.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('crm.quotation-breakup-templates.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-table me-2"></i>
                                    <span>Breakup Templates</span>
                                </a>
                            </li>
                        @endif
                    @endcan        
                          
                        </ul>
                    </div>
                </li>
                @endcanany

                                @php
                                    // Self-service routes (can exist even for non-HR roles)
                                    $myLeaveRoute = $pickRoute([
                                        'hr.my.leave.index',
                                        'hr.my-leave.index',
                                        'hr.self.leave.index',
                                        'hr.self-service.leave.index',
                                        'hr.leave.my.index',
                                        'hr.leave.my',
                                    ]);
                
                                    $myLeaveBalanceRoute = $pickRoute([
                                        'hr.my.leave.balance',
                                        'hr.my-leave.balance',
                                        'hr.self.leave.balance',
                                        'hr.self-service.leave.balance',
                                        'hr.leave.my.balance',
                                        'hr.leave.my-balance',
                                    ]);
                
                                    $hrAdminAccess = auth()->check() && auth()->user()->canany([
                                        'hr.dashboard.view',
                                        'hr.employee.view',
                                        'hr.attendance.view',
                                        'hr.leave.view',
                                        'hr.payroll.view',
                                    ]);
                
                                    // Only show Self Service if logged-in user is linked to an HR Employee record
                                    $myEmployeeExists = false;
                                    if (auth()->check() && class_exists(\App\Models\Hr\HrEmployee::class)) {
                                        $myEmployeeExists = \App\Models\Hr\HrEmployee::query()
                                            ->where('user_id', auth()->id())
                                            ->exists();
                                    }
                
                                    $hrSelfServiceAccess = auth()->check() && $myEmployeeExists && ($myLeaveRoute || $myLeaveBalanceRoute);
                                @endphp
                @if($hrAdminAccess || $hrSelfServiceAccess)
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('hr') }}"
                            aria-expanded="{{ $openHr ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('hr') }}"
                            data-erp-section-toggle
                            data-erp-section-key="hr">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-people-fill"></i>
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">HR</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>
                    <div id="{{ $secId('hr') }}" class="collapse {{ $openHr ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                    {{-- HR Dashboard --}}
                    @can('hr.dashboard.view')
                        @if(Route::has('hr.dashboard'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('hr.dashboard') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('hr.dashboard') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-speedometer2 me-2"></i><span>HR Dashboard</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    {{-- Self Service --}}
                    @if($hrSelfServiceAccess)
                        <li data-erp-menu-item class="nav-item mt-2 mb-1 px-3 text-muted text-uppercase fw-semibold" style="font-size: 0.65rem;">
                            Self Service
                        </li>

                        @if($myLeaveRoute)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($myLeaveRoute) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['hr.my.leave.*','hr.my-leave.*','hr.self.leave.*','hr.self-service.leave.*']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-calendar2-check me-2"></i><span>My Leave</span>
                                </a>
                            </li>
                        @endif

                        @if($myLeaveBalanceRoute)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($myLeaveBalanceRoute) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['hr.my.leave.balance','hr.my-leave.balance','hr.self.leave.balance','hr.self-service.leave.balance']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-pie-chart me-2"></i><span>My Leave Balance</span>
                                </a>
                            </li>
                        @endif
                    @endif

                    {{-- Admin / HR Operations --}}
                    @if($hrAdminAccess)
                        <li data-erp-menu-item class="nav-item mt-2 mb-1 px-3 text-muted text-uppercase fw-semibold" style="font-size: 0.65rem;">
                            Operations
                        </li>

                        {{-- Employees --}}
                        @can('hr.employee.view')
                            @if(Route::has('hr.employees.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.employees.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                              {{ $isRoute('hr.employees.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-person-vcard me-2"></i><span>Employees</span>
                                    </a>
                                </li>
                            @endif
                        @endcan

                        {{-- Attendance --}}
                        @can('hr.attendance.view')
                            @if(Route::has('hr.attendance.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.attendance.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                              {{ $isRoute('hr.attendance.index') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-calendar-check me-2"></i><span>Attendance</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.attendance.monthly'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.attendance.monthly') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.attendance.monthly') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-calendar3-week me-2"></i><span>Attendance Monthly</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.attendance.report'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.attendance.report') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.attendance.report') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-clipboard-data me-2"></i><span>Attendance Report</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.attendance.ot-approval'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.attendance.ot-approval') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.attendance.ot-approval*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-hourglass-top me-2"></i><span>OT Approval</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.attendance.regularization'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.attendance.regularization') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.attendance.regularization*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-arrow-repeat me-2"></i><span>Regularization</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.attendance.bulk-entry'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.attendance.bulk-entry') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.attendance.bulk-entry') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-pencil-square me-2"></i><span>Bulk Attendance Entry</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.attendance.import-punches'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.attendance.import-punches') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.attendance.import-punches') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-upload me-2"></i><span>Import Punches</span>
                                    </a>
                                </li>
                            @endif
                        @endcan

                        {{-- Leave --}}
                        @can('hr.leave.view')
                            @if(Route::has('hr.leave.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.leave.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                              {{ $isRoute('hr.leave.index') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-calendar-minus me-2"></i><span>Leave Management</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.leave.pending'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.leave.pending') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.leave.pending') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-hourglass-split me-2"></i><span>Leave Pending Approval</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.leave.calendar'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.leave.calendar') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.leave.calendar') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-calendar3 me-2"></i><span>Leave Calendar</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.leave.balance-report'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.leave.balance-report') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.leave.balance-report') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-bar-chart-steps me-2"></i><span>Leave Balance Report</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.leave-applications.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.leave-applications.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.leave-applications.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-journal-check me-2"></i><span>Leave Applications</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.leave.year-end'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.leave.year-end') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.leave.year-end') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-calendar2-x me-2"></i><span>Year End Leave Process</span>
                                    </a>
                                </li>
                            @endif
                        @endcan

                        {{-- Payroll --}}
                        @can('hr.payroll.view')
                            @if(Route::has('hr.payroll.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.payroll.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                              {{ $isRoute('hr.payroll.index') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-cash-stack me-2"></i><span>Payroll</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.payroll.create-period'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.payroll.create-period') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.payroll.create-period') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-plus-square me-2"></i><span>Create Payroll Period</span>
                                    </a>
                                </li>
                            @endif
                        @endcan

                        {{-- Loans, Advances, Tax, Reports --}}
                        @canany(['hr.employee.view','hr.leave.view','hr.payroll.view'])
                            @if(Route::has('hr.loans.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.loans.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                              {{ $isRoute('hr.loans.index') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-cash-coin me-2"></i><span>Loans</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.loans.employee-loans.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.loans.employee-loans.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.loans.employee-loans.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-list-check me-2"></i><span>Employee Loans</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.advances.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.advances.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                              {{ $isRoute('hr.advances.index') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-wallet2 me-2"></i><span>Advances</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.advances.salary-advances.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.advances.salary-advances.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.advances.salary-advances.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-bank me-2"></i><span>Salary Advances</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.tax.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.tax.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                              {{ $isRoute('hr.tax.index') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-receipt-cutoff me-2"></i><span>Tax Dashboard</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.tax.declarations.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.tax.declarations.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.tax.declarations.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-file-earmark-text me-2"></i><span>Tax Declarations</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.reports.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.reports.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                              {{ $isRoute('hr.reports.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-graph-up-arrow me-2"></i><span>HR Reports</span>
                                    </a>
                                </li>
                            @endif
                        @endcanany

                        {{-- HR Masters --}}
                        @canany(['hr.employee.view'])
                            <li data-erp-menu-item class="nav-item mt-2 mb-1 px-3 text-muted text-uppercase fw-semibold" style="font-size: 0.65rem;">
                                HR Masters
                            </li>

                            @if(Route::has('hr.designations.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.designations.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.designations.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-person-workspace me-2"></i><span>Designations</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.grades.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.grades.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.grades.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-sort-numeric-up me-2"></i><span>Grades</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.shifts.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.shifts.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.shifts.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-clock-history me-2"></i><span>Shifts</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.work-locations.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.work-locations.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.work-locations.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-geo-alt me-2"></i><span>Work Locations</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.attendance-policies.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.attendance-policies.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.attendance-policies.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-clipboard2-pulse me-2"></i><span>Attendance Policies</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.leave-types.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.leave-types.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.leave-types.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-calendar2-range me-2"></i><span>Leave Types</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.leave-policies.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.leave-policies.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.leave-policies.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-shield-check me-2"></i><span>Leave Policies</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.holiday-calendars.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.holiday-calendars.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.holiday-calendars.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-calendar-heart me-2"></i><span>Holiday Calendar</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.salary-components.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.salary-components.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.salary-components.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-diagram-2 me-2"></i><span>Salary Components</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.salary-structures.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.salary-structures.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.salary-structures.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-layers me-2"></i><span>Salary Structures</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.loan-types.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.loan-types.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.loan-types.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-credit-card-2-front me-2"></i><span>Loan Types</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.settings.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.settings.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.settings.index') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-gear me-2"></i><span>HR Settings</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.settings.pf-slabs.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.settings.pf-slabs.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.settings.pf-slabs.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-grid-3x3-gap me-2"></i><span>PF Slabs</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.settings.esi-slabs.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.settings.esi-slabs.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.settings.esi-slabs.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-grid-3x3-gap me-2"></i><span>ESI Slabs</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.settings.pt-slabs.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.settings.pt-slabs.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.settings.pt-slabs.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-grid-3x3-gap me-2"></i><span>PT Slabs</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.settings.tds-slabs.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.settings.tds-slabs.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.settings.tds-slabs.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-grid-3x3-gap me-2"></i><span>TDS Slabs</span>
                                    </a>
                                </li>
                            @endif

                            @if(Route::has('hr.settings.lwf-slabs.index'))
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('hr.settings.lwf-slabs.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('hr.settings.lwf-slabs.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-grid-3x3-gap me-2"></i><span>LWF Slabs</span>
                                    </a>
                                </li>
                            @endif
                        @endcanany
                    @endif
                        </ul>
                    </div>
                </li>
                @endif
                @canany(['purchase.indent.view', 'purchase.rfq.view', 'purchase.order.view', 'purchase.bill.view'])
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('purchase') }}"
                            aria-expanded="{{ $openPurchase ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('purchase') }}"
                            data-erp-section-toggle
                            data-erp-section-key="purchase">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-cart-check"></i>
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Purchase</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>
                    <div id="{{ $secId('purchase') }}" class="collapse {{ $openPurchase ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                    @can('purchase.indent.view')
                        @if(\Illuminate\Support\Facades\Route::has('purchase-indents.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('purchase-indents.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('purchase-indents.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-journal-text me-2"></i>
                                    <span>Purchase Indents</span>
                                </a>
                            </li>
                        @endif
                    @endcan
@if(\Illuminate\Support\Facades\Route::has('purchase-orders.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('purchase-orders.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('purchase-orders.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-cart-check me-2"></i>
                                    <span>Purchase Orders</span>
                                </a>
                            </li>
                        @endif
                    @can('purchase.rfq.view')
                        @if(\Illuminate\Support\Facades\Route::has('purchase-rfqs.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('purchase-rfqs.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('purchase-rfqs.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-send-check me-2"></i>
                                    <span>Purchase RFQs</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('purchase.order.view')
                        @if(\Illuminate\Support\Facades\Route::has('purchase-orders.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('purchase-orders.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('purchase-orders.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-cart-check me-2"></i>
                                    <span>Purchase Orders</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('purchase.bill.view')
                        @if(\Illuminate\Support\Facades\Route::has('purchase.bills.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('purchase.bills.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('purchase.bills.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-receipt me-2"></i>
                                    <span>Purchase Bills</span>
                                </a>
                            </li>
                        @endif
                    @endcan
                        </ul>
                    </div>
                </li>
                @endcanany
                @canany([
                    'store.material_receipt.view',
                    'store.stock.view',
                    'store.requisition.view',
                    'store.issue.view',
                    'store.return.view',
                    'store.stock.adjustment.view',
                    'store.gatepass.view',
                    'machinery.machine.view',
                ])
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('store') }}"
                            aria-expanded="{{ $openStore ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('store') }}"
                            data-erp-section-toggle
                            data-erp-section-key="store">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-boxes"></i>
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Store</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>
                    <div id="{{ $secId('store') }}" class="collapse {{ $openStore ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                    @can('store.stock.view')
                        @if(\Illuminate\Support\Facades\Route::has('store.dashboard'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('store.dashboard') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('store.dashboard') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-speedometer2 me-2"></i>
                                    <span>Store Dashboard</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.material_receipt.view')
                        @if(\Illuminate\Support\Facades\Route::has('material-receipts.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('material-receipts.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('material-receipts.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-journal-arrow-down me-2"></i>
                                    <span>GRN / Material Receipts</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.stock.view')
                        @if(\Illuminate\Support\Facades\Route::has('store-stock.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('store-stock.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('store-stock.index') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-layers me-2"></i>
                                    <span>Store Stock (Filtered)</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.stock.view')
                        @if(\Illuminate\Support\Facades\Route::has('store-stock-items.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('store-stock-items.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('store-stock-items.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-box2 me-2"></i>
                                    <span>Stock Items &amp; Pieces</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.stock.view')
                        @if(\Illuminate\Support\Facades\Route::has('store-stock-summary.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('store-stock-summary.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('store-stock-summary.index') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-collection me-2"></i>
                                    <span>Store Stock Summary</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.stock.view')
                        @if(\Illuminate\Support\Facades\Route::has('store-stock-register.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('store-stock-register.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('store-stock-register.index') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-journal-text me-2"></i>
                                    <span>Stock Register</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.stock.view')
                        @if(\Illuminate\Support\Facades\Route::has('store-low-stock.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('store-low-stock.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('store-low-stock.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <span>Low Stock</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.stock.view')
                        @if(\Illuminate\Support\Facades\Route::has('store-remnants.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('store-remnants.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('store-remnants.index') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-grid-3x3-gap me-2"></i>
                                    <span>Remnant Library</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.requisition.view')
                        @if(\Illuminate\Support\Facades\Route::has('store-requisitions.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('store-requisitions.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('store-requisitions.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-clipboard-check me-2"></i>
                                    <span>Store Requisitions</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.issue.view')
                        @if(\Illuminate\Support\Facades\Route::has('store-issues.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('store-issues.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('store-issues.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-box-arrow-right me-2"></i>
                                    <span>Store Issues</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.return.view')
                        @if(\Illuminate\Support\Facades\Route::has('store-returns.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('store-returns.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('store-returns.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-arrow-90deg-left me-2"></i>
                                    <span>Store Returns</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.stock.adjustment.view')
                        @if(\Illuminate\Support\Facades\Route::has('store-stock-adjustments.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('store-stock-adjustments.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('store-stock-adjustments.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-sliders2 me-2"></i>
                                    <span>Stock Adjustments / Openings</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('store.gatepass.view')
                        @if(\Illuminate\Support\Facades\Route::has('gate-passes.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('gate-passes.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('gate-passes.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-truck me-2"></i>
                                    <span>Gate Passes</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                 @canany([
    'machinery.machine.view',
    'machinery.assignment.view',
    'machinery.maintenance_plan.view',
    'machinery.maintenance_log.view',
    'machinery.breakdown.view',
    'machinery.calibration.view',
    'machinery.maintenance.reports'
	])
	<li data-erp-menu-item class="nav-item">
    <div class="nav-link disabled fw-bold text-uppercase pb-1" style="font-size: 0.75rem; opacity: 0.6;">
        <i class="bi bi-gear-fill me-1"></i>
        <span>Machinery Management</span>
    </div>

    <ul class="nav flex-column ms-2">
        @can('machinery.machine.view')
            @if(\Illuminate\Support\Facades\Route::has('machines.index'))
                <li class="nav-item">
                    <a data-erp-menu-item class="nav-link {{ $isRoute('machines.*') ? 'active' : '' }}"
                       href="{{ route('machines.index') }}">
                        <i class="bi bi-circle me-2" style="font-size: 0.5rem;"></i> Machines
                    </a>
                </li>
            @endif

            @if(\Illuminate\Support\Facades\Route::has('machinery-bills.index'))
                <li class="nav-item">
                    <a data-erp-menu-item class="nav-link {{ $isRoute('machinery-bills.*') ? 'active' : '' }}"
                       href="{{ route('machinery-bills.index') }}">
                        <i class="bi bi-circle me-2" style="font-size: 0.5rem;"></i> Machinery Bills
                    </a>
                </li>
            @endif
        @endcan

        @can('machinery.assignment.view')
            @if(\Illuminate\Support\Facades\Route::has('machine-assignments.index'))
                <li class="nav-item">
                    <a data-erp-menu-item class="nav-link {{ $isRoute('machine-assignments.*') ? 'active' : '' }}"
                       href="{{ route('machine-assignments.index') }}">
                        <i class="bi bi-circle me-2" style="font-size: 0.5rem;"></i> Issued Machines
                    </a>
                </li>
            @endif
        @endcan

        @can('machinery.maintenance_plan.view')
            @if(\Illuminate\Support\Facades\Route::has('maintenance.plans.index'))
                <li class="nav-item">
                    <a data-erp-menu-item class="nav-link {{ $isRoute('maintenance.plans.*') ? 'active' : '' }}"
                       href="{{ route('maintenance.plans.index') }}">
                        <i class="bi bi-circle me-2" style="font-size: 0.5rem;"></i> Maintenance Plans
                    </a>
                </li>
            @endif
        @endcan

        @can('machinery.maintenance_log.view')
            @if(\Illuminate\Support\Facades\Route::has('maintenance.logs.index'))
                <li class="nav-item">
                    <a data-erp-menu-item class="nav-link {{ $isRoute('maintenance.logs.*') ? 'active' : '' }}"
                       href="{{ route('maintenance.logs.index') }}">
                        <i class="bi bi-circle me-2" style="font-size: 0.5rem;"></i> Maintenance Logs
                    </a>
                </li>
            @endif
        @endcan

        @can('machinery.breakdown.view')
            @if(\Illuminate\Support\Facades\Route::has('maintenance.breakdowns.index'))
                <li class="nav-item">
                    <a data-erp-menu-item class="nav-link {{ $isRoute('maintenance.breakdowns.*') ? 'active' : '' }}"
                       href="{{ route('maintenance.breakdowns.index') }}">
                        <i class="bi bi-circle me-2" style="font-size: 0.5rem;"></i> Breakdown Register
                    </a>
                </li>
            @endif
        @endcan

        @can('machinery.calibration.view')
            @if(\Illuminate\Support\Facades\Route::has('machine-calibrations.index'))
                <li class="nav-item">
                    <a data-erp-menu-item class="nav-link {{ $isRoute('machine-calibrations.*') ? 'active' : '' }}"
                       href="{{ route('machine-calibrations.index') }}">
                        <i class="bi bi-circle me-2" style="font-size: 0.5rem;"></i> Calibrations
                    </a>
                </li>
            @endif
        @endcan

        @can('machinery.maintenance.reports')
            {{-- Show either report link if routes exist --}}
            @if(\Illuminate\Support\Facades\Route::has('maintenance.reports.issued-register'))
                <li class="nav-item">
                    <a data-erp-menu-item class="nav-link {{ $isRoute('maintenance.reports.*') ? 'active' : '' }}"
                       href="{{ route('maintenance.reports.issued-register') }}">
                        <i class="bi bi-circle me-2" style="font-size: 0.5rem;"></i> Issued Register
                    </a>
                </li>
            @endif

            @if(\Illuminate\Support\Facades\Route::has('maintenance.reports.cost-analysis'))
                <li class="nav-item">
                    <a data-erp-menu-item class="nav-link {{ $isRoute('maintenance.reports.*') ? 'active' : '' }}"
                       href="{{ route('maintenance.reports.cost-analysis') }}">
                        <i class="bi bi-circle me-2" style="font-size: 0.5rem;"></i> Cost Analysis
                    </a>
                </li>
            @endif
        @endcan
    </ul>
	</li>
	@endcanany
                        </ul>
                    </div>
                </li>
                @endcanany
                @canany(['accounting.accounts.view', 'accounting.vouchers.view', 'accounting.vouchers.create', 'accounting.reports.view'])
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('accounting') }}"
                            aria-expanded="{{ $openAccounting ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('accounting') }}"
                            data-erp-section-toggle
                            data-erp-section-key="accounting">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-journal-text"></i>
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Accounting</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>
                    <div id="{{ $secId('accounting') }}" class="collapse {{ $openAccounting ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                    @can('accounting.accounts.view')
                        @if(\Illuminate\Support\Facades\Route::has('accounting.accounts.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('accounting.accounts.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('accounting.accounts.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-journal me-2"></i>
                                    <span>Chart of Accounts</span>
                                </a>
                            </li>
                        @endif
                    @endcan
				
              			@if(\Illuminate\Support\Facades\Route::has('accounting.account-groups.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('accounting.account-groups.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute('accounting.account-groups.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-diagram-3 me-2"></i>
                                    <span>Account Groups</span>
                                </a>
                            </li>
                        @endif

                        @if(\Illuminate\Support\Facades\Route::has('accounting.account-types.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('accounting.account-types.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute('accounting.account-types.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-tags me-2"></i>
                                    <span>Account Types</span>
                                </a>
                            </li>
                        @endif
                        @if(\Illuminate\Support\Facades\Route::has('accounting.tds-sections.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('accounting.tds-sections.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute('accounting.tds-sections.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-percent me-2"></i>
                                    <span>TDS Sections</span>
                                </a>
                            </li>
                        @endif
                        @if(\Illuminate\Support\Facades\Route::has('accounting.voucher-series.index'))
                            @can('accounting.accounts.view')
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('accounting.voucher-series.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                              {{ $isRoute('accounting.voucher-series.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-hash me-2"></i>
                                        <span>Voucher Series</span>
                                    </a>
                                </li>
                            @endcan
                        @endif



                    
                          
                    @can('accounting.vouchers.view')
                        @if(\Illuminate\Support\Facades\Route::has('accounting.vouchers.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('accounting.vouchers.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('accounting.vouchers.*') && !request()->boolean('wip_to_cogs') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-receipt me-2"></i>
                                    <span>Vouchers</span>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="{{ route('accounting.vouchers.index', ['wip_to_cogs' => 1]) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ ($isRoute('accounting.vouchers.index') && request()->boolean('wip_to_cogs')) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-arrow-left-right me-2"></i>
                                    <span>WIP → COGS Drafts</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                        {{-- Billing / RA Bills --}}
                        @if(\Illuminate\Support\Facades\Route::has('accounting.subcontractor-ra.index'))
                            @can('subcontractor_ra.view')
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('accounting.subcontractor-ra.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                              {{ $isRoute('accounting.subcontractor-ra.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-person-workspace me-2"></i>
                                        <span>Subcontractor RA Bills</span>
                                    </a>
                                </li>
                            @endcan
                        @endif

                        @if(\Illuminate\Support\Facades\Route::has('accounting.client-ra.index'))
                            @can('client_ra.view')
                                <li class="nav-item">
                                    <a data-erp-menu-item href="{{ route('accounting.client-ra.index') }}"
                                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                              {{ $isRoute('accounting.client-ra.*') ? 'active' : 'text-body-secondary' }}">
                                        <i class="bi bi-receipt-cutoff me-2"></i>
                                        <span>Client RA Bills</span>
                                    </a>
                                </li>
                            @endcan
                        @endif

					{{-- Credit / Debit Notes --}}
				@can('accounting.vouchers.view')
   				 @if(\Illuminate\Support\Facades\Route::has('accounting.purchase-debit-notes.index'))
   			     <li class="nav-item">
   		         <a data-erp-menu-item href="{{ route('accounting.purchase-debit-notes.index') }}"
    	           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                      {{ $isRoute('accounting.purchase-debit-notes.*') ? 'active' : 'text-body-secondary' }}">
                <i class="bi bi-journal-minus me-2"></i>
                <span>Purchase Debit Notes</span>
    	        </a>
    		    </li>
  				  @endif

  			  @if(\Illuminate\Support\Facades\Route::has('accounting.sales-credit-notes.index'))
    		    <li class="nav-item">
         		   <a data-erp-menu-item href="{{ route('accounting.sales-credit-notes.index') }}"
               class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                      {{ $isRoute('accounting.sales-credit-notes.*') ? 'active' : 'text-body-secondary' }}">
                <i class="bi bi-journal-arrow-down me-2"></i>
                <span>Sales Credit Notes</span>
        	    </a>
      			  </li>
    			@endif
				@endcan
              
                    {{-- Bank/Cash Vouchers (Payment/Receipt) --}}
                    @php
                        $paymentCreateRoute = $pickRoute(['accounting.payments.create', 'payments.create']);
                        $receiptCreateRoute = $pickRoute(['accounting.receipts.create', 'receipts.create']);
                        $onAccountRoute     = $pickRoute(['accounting.receipts.on-account.index', 'receipts.on-account.index']);
                    @endphp

                    @can('accounting.vouchers.create')
                        @if($paymentCreateRoute || $receiptCreateRoute || $onAccountRoute)
                            <li data-erp-menu-item class="nav-item mt-2 mb-1 px-3 text-muted text-uppercase fw-semibold" style="font-size: 0.65rem;">
                                Bank &amp; Cash
                            </li>
                        @endif

                        @if($paymentCreateRoute)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($paymentCreateRoute) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.payments.*', 'payments.*']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-arrow-up-right-circle me-2"></i>
                                    <span>New Payment</span>
                                </a>
                            </li>
                        @endif

                        @if($receiptCreateRoute)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($receiptCreateRoute) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.receipts.*', 'receipts.*']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-arrow-down-left-circle me-2"></i>
                                    <span>New Receipt</span>
                                </a>
                            </li>
                        @endif

                        @if($onAccountRoute)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($onAccountRoute) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.receipts.on-account.*', 'receipts.on-account.*']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-link-45deg me-2"></i>
                                    <span>On-Account Adjustment</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    {{-- Reports --}}
                    @php
                        $rTrialBalance        = $pickRoute(['accounting.reports.trial-balance', 'reports.trial-balance']);
                        $rLedger              = $pickRoute(['accounting.reports.ledger', 'reports.ledger']);
                        $rDayBook             = $pickRoute(['accounting.reports.day-book', 'reports.day-book']);
                        $rProfitLoss          = $pickRoute(['accounting.reports.profit-loss', 'reports.profit-loss']);
                        $rBalanceSheet        = $pickRoute(['accounting.reports.balance-sheet', 'reports.balance-sheet']);
                        $rInventoryValuation  = $pickRoute(['accounting.reports.inventory-valuation', 'reports.inventory-valuation']);
                        $rProjectCostSheet    = $pickRoute(['accounting.reports.project-cost-sheet', 'reports.project-cost-sheet']);
                        $rGstSummary          = $pickRoute(['accounting.reports.gst-summary', 'reports.gst-summary']);
                        $rGstPurchaseRegister = $pickRoute(['accounting.reports.gst-purchase-register', 'reports.gst-purchase-register']);
                        $rGstSalesRegister    = $pickRoute(['accounting.reports.gst-sales-register', 'reports.gst-sales-register']);
                        $rGstVoucherRegister  = $pickRoute(['accounting.reports.gst-voucher-register', 'reports.gst-voucher-register']);
                        $rGstHsnPurchaseSummary = $pickRoute(['accounting.reports.gst-hsn-purchase-summary', 'reports.gst-hsn-purchase-summary']);
                        $rGstHsnSalesSummary    = $pickRoute(['accounting.reports.gst-hsn-sales-summary', 'reports.gst-hsn-sales-summary']);
                        $rSupplierOutstanding = $pickRoute(['accounting.reports.supplier-outstanding', 'reports.supplier-outstanding']);
                        $rClientOutstanding   = $pickRoute(['accounting.reports.client-outstanding', 'reports.client-outstanding']);
                        $rSupplierAgeing      = $pickRoute(['accounting.reports.supplier-ageing', 'reports.supplier-ageing']);
                        $rClientAgeing        = $pickRoute(['accounting.reports.client-ageing', 'reports.client-ageing']);
                        $rCashFlow            = $pickRoute(['accounting.reports.cash-flow', 'reports.cash-flow']);
                        $rFundFlow            = $pickRoute(['accounting.reports.fund-flow', 'reports.fund-flow']);
                        $rUnbalancedVouchers  = $pickRoute(['accounting.reports.unbalanced-vouchers', 'reports.unbalanced-vouchers']);
                        $rTdsCertificates     = $pickRoute(['accounting.reports.tds-certificates', 'reports.tds-certificates']);
                    @endphp

                    @can('accounting.reports.view')
                        @if(
                            $rTrialBalance || $rLedger || $rDayBook || $rProfitLoss || $rBalanceSheet ||
                            $rInventoryValuation || $rProjectCostSheet ||
                            $rSupplierOutstanding || $rClientOutstanding || $rSupplierAgeing || $rClientAgeing ||
                            $rCashFlow || $rFundFlow || $rUnbalancedVouchers || $rTdsCertificates || $rGstSummary ||
                            $rGstPurchaseRegister || $rGstSalesRegister || $rGstVoucherRegister ||
                            $rGstHsnPurchaseSummary || $rGstHsnSalesSummary
                        )
                            <li data-erp-menu-item class="nav-item mt-2 mb-1 px-3 text-muted text-uppercase fw-semibold" style="font-size: 0.65rem;">
                                Reports
                            </li>
                        @endif

                        @if($rTrialBalance)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rTrialBalance) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.trial-balance', 'reports.trial-balance']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-bar-chart-line me-2"></i>
                                    <span>Trial Balance</span>
                                </a>
                            </li>
                        @endif

                        @if($rLedger)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rLedger) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.ledger', 'reports.ledger']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-journals me-2"></i>
                                    <span>Ledger</span>
                                </a>
                            </li>
                        @endif

                        @if($rDayBook)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rDayBook) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.day-book', 'reports.day-book']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-calendar3 me-2"></i>
                                    <span>Day Book</span>
                                </a>
                            </li>
                        @endif

                        @if($rProfitLoss)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rProfitLoss) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.profit-loss', 'reports.profit-loss']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-graph-up-arrow me-2"></i>
                                    <span>Profit &amp; Loss</span>
                                </a>
                            </li>
                        @endif

                        @if($rBalanceSheet)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rBalanceSheet) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.balance-sheet', 'reports.balance-sheet']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-columns-gap me-2"></i>
                                    <span>Balance Sheet</span>
                                </a>
                            </li>
                        @endif

                        @if($rInventoryValuation)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rInventoryValuation) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.inventory-valuation', 'reports.inventory-valuation']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-box-seam me-2"></i>
                                    <span>Inventory Valuation</span>
                                </a>
                            </li>
                        @endif

                        @if($rProjectCostSheet)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rProjectCostSheet) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.project-cost-sheet*', 'reports.project-cost-sheet*']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-kanban me-2"></i>
                                    <span>Project Cost Sheet</span>
                                </a>
                            </li>
                        @endif

                        @if($rGstSummary)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rGstSummary) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.gst-summary', 'reports.gst-summary']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-receipt me-2"></i>
                                    <span>GST Summary</span>
                                </a>
                            </li>
                        @endif

                        @if($rGstPurchaseRegister)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rGstPurchaseRegister) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.gst-purchase-register', 'reports.gst-purchase-register']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-bag-check me-2"></i>
                                    <span>GST Purchase Register</span>
                                </a>
                            </li>
                        @endif

                        @if($rGstSalesRegister)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rGstSalesRegister) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.gst-sales-register', 'reports.gst-sales-register']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-cart-check me-2"></i>
                                    <span>GST Sales Register</span>
                                </a>
                            </li>
                        @endif

                        @if($rGstVoucherRegister)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rGstVoucherRegister) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.gst-voucher-register', 'reports.gst-voucher-register']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    <span>GST Voucher Register</span>
                                </a>
                            </li>
                        @endif

                        
                        @if($rTdsCertificates)
                            <li class="nav-item">
                                <a href="{{ route($rTdsCertificates, ['direction' => 'receivable']) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ ($isRoute(['accounting.reports.tds-certificates*', 'reports.tds-certificates*']) && request('direction', 'receivable') !== 'payable') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    <span>TDS Certificates (Receivable)</span>
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="{{ route($rTdsCertificates, ['direction' => 'payable']) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ ($isRoute(['accounting.reports.tds-certificates*', 'reports.tds-certificates*']) && request('direction') === 'payable') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    <span>TDS Certificates (Payable)</span>
                                </a>
                            </li>
                        @endif

@if($rGstHsnPurchaseSummary)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rGstHsnPurchaseSummary) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.gst-hsn-purchase-summary', 'reports.gst-hsn-purchase-summary']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-list-check me-2"></i>
                                    <span>GST HSN Summary (Purchase)</span>
                                </a>
                            </li>
                        @endif

                        @if($rGstHsnSalesSummary)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rGstHsnSalesSummary) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.gst-hsn-sales-summary', 'reports.gst-hsn-sales-summary']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-list-check me-2"></i>
                                    <span>GST SAC/HSN Summary (Sales)</span>
                                </a>
                            </li>
                        @endif

                        @if($rSupplierOutstanding)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rSupplierOutstanding) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.supplier-outstanding', 'reports.supplier-outstanding']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-person-down me-2"></i>
                                    <span>Supplier Outstanding</span>
                                </a>
                            </li>
                        @endif

                        @if($rClientOutstanding)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rClientOutstanding) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.client-outstanding', 'reports.client-outstanding']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-person-up me-2"></i>
                                    <span>Client Outstanding</span>
                                </a>
                            </li>
                        @endif

                        @if($rSupplierAgeing)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rSupplierAgeing) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.supplier-ageing*', 'reports.supplier-ageing*']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-hourglass-split me-2"></i>
                                    <span>Supplier Ageing</span>
                                </a>
                            </li>
                        @endif

                        @if($rClientAgeing)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rClientAgeing) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.client-ageing*', 'reports.client-ageing*']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-hourglass-top me-2"></i>
                                    <span>Client Ageing</span>
                                </a>
                            </li>
                        @endif

                        @if($rCashFlow)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rCashFlow) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.cash-flow', 'reports.cash-flow']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-cash-coin me-2"></i>
                                    <span>Cash Flow</span>
                                </a>
                            </li>
                        @endif

                        @if($rFundFlow)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rFundFlow) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.fund-flow', 'reports.fund-flow']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-arrow-left-right me-2"></i>
                                    <span>Fund Flow</span>
                                </a>
                            </li>
                        @endif

                        @if($rUnbalancedVouchers)
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route($rUnbalancedVouchers) }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                          {{ $isRoute(['accounting.reports.unbalanced-vouchers', 'reports.unbalanced-vouchers']) ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <span>Unbalanced Vouchers</span>
                                </a>
                            </li>
                        @endif
                    @endcan
                        </ul>
                    </div>
                </li>
                @endcanany
                @canany(['production.activity.view', 'production.activity.create', 'production.activity.update', 'production.activity.delete'])
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('production') }}"
                            aria-expanded="{{ $openProduction ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('production') }}"
                            data-erp-section-toggle
                            data-erp-section-key="production">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-diagram-3"></i>
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Production</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>
                    <div id="{{ $secId('production') }}" class="collapse {{ $openProduction ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                    @can('production.activity.view')
                        @if(\Illuminate\Support\Facades\Route::has('production.activities.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('production.activities.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('production.activities.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-diagram-3 me-2"></i>
                                    <span>Activity Master</span>
                                </a>
                            </li>
                        @endif
                    @endcan
                        </ul>
                    </div>
                </li>
                @endcanany
                @can('project.project.view')
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('projects') }}"
                            aria-expanded="{{ $openProjects ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('projects') }}"
                            data-erp-section-toggle
                            data-erp-section-key="projects">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-kanban"></i>
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Projects</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>
                    <div id="{{ $secId('projects') }}" class="collapse {{ $openProjects ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                    @if(\Illuminate\Support\Facades\Route::has('projects.index'))
                        <li class="nav-item">
                            <a data-erp-menu-item href="{{ route('projects.index') }}"
                               class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                      {{ $isRoute('projects.*') ? 'active' : 'text-body-secondary' }}">
                                <i class="bi bi-kanban me-2"></i>
                                <span>Projects</span>
                            </a>
                         </li>
                    @endif

                    @if(\Illuminate\Support\Facades\Route::has('bom-templates.index'))
                        <li class="nav-item">
                            <a data-erp-menu-item href="{{ route('bom-templates.index') }}"
                               class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                      {{ $isRoute('bom-templates.*') ? 'active' : 'text-body-secondary' }}">
                                <i class="bi bi-diagram-3-fill me-2"></i>
                                <span>BOM Templates</span>
                            </a>
                        </li>
                    @endif

                    {{-- Project Production (Phase B–G) - Only show when inside a project --}}
                    @if($currentProject)
                        @php
                            $rProdDash   = $pickRoute(['projects.production-dashboard.index']);
                            $rProdPlans  = $pickRoute(['projects.production-plans.index']);
                            $rProdDprs   = $pickRoute(['projects.production-dprs.index']);
                            $rProdQc     = $pickRoute(['projects.production-qc.index']);
                            $rProdBill   = $pickRoute(['projects.production-billing.index']);
                          	$rProdDispatch = $pickRoute(['projects.production-dispatches.index']);
                                $rProdTrace  = $pickRoute(['projects.production-traceability.index']);
                        @endphp

                        @canany(['production.report.view','production.plan.view','production.dpr.view','production.qc.perform','production.billing.view','production.traceability.view'])
                            <li data-erp-menu-item class="nav-item mt-2 mb-1 px-3 text-muted text-uppercase fw-semibold" style="font-size: 0.65rem;">
                                Project Production
                            </li>

                            @can('production.report.view')
                                @if($rProdDash)
                                    <li class="nav-item">
                                        <a data-erp-menu-item href="{{ route($rProdDash, $currentProject) }}"
                                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                                  {{ $isRoute('projects.production-dashboard.*') ? 'active' : 'text-body-secondary' }}">
                                            <i class="bi bi-speedometer2 me-2"></i>
                                            <span>Production Dashboard</span>
                                        </a>
                                    </li>
                                @endif
                            @endcan

                            @can('production.plan.view')
                                @if($rProdPlans)
                                    <li class="nav-item">
                                        <a data-erp-menu-item href="{{ route($rProdPlans, $currentProject) }}"
                                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                                  {{ $isRoute('projects.production-plans.*') ? 'active' : 'text-body-secondary' }}">
                                            <i class="bi bi-clipboard2-check me-2"></i>
                                            <span>Production Plans</span>
                                        </a>
                                    </li>
                                @endif
                            @endcan

                            @can('production.dpr.view')
                                @if($rProdDprs)
                                    <li class="nav-item">
                                        <a data-erp-menu-item href="{{ route($rProdDprs, $currentProject) }}"
                                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                                  {{ $isRoute('projects.production-dprs.*') ? 'active' : 'text-body-secondary' }}">
                                            <i class="bi bi-journal-check me-2"></i>
                                            <span>Production DPR</span>
                                        </a>
                                    </li>
                                @endif
                            @endcan

                            @can('production.qc.perform')
                                @if($rProdQc)
                                    <li class="nav-item">
                                        <a data-erp-menu-item href="{{ route($rProdQc, $currentProject) }}"
                                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                                  {{ $isRoute('projects.production-qc.*') ? 'active' : 'text-body-secondary' }}">
                                            <i class="bi bi-shield-check me-2"></i>
                                            <span>QC Pending</span>
                                        </a>
                                    </li>
                                @endif
                            @endcan

                            @can('production.billing.view')
                                @if($rProdBill)
                                    <li class="nav-item">
                                        <a data-erp-menu-item href="{{ route($rProdBill, $currentProject) }}"
                                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                                  {{ $isRoute('projects.production-billing.*') ? 'active' : 'text-body-secondary' }}">
                                            <i class="bi bi-receipt me-2"></i>
                                            <span>Production Billing</span>
                                        </a>
                                    </li>
                                @endif
                            @endcan
                          
                          	@can('production.dispatch.view')
                                @if($rProdDispatch)
                                    <li class="nav-item">
                                        <a data-erp-menu-item href="{{ route($rProdDispatch, $currentProject) }}"
                                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                                  {{ $isRoute('projects.production-dispatches.*') ? 'active' : 'text-body-secondary' }}">
                                            <i class="bi bi-truck me-2"></i>
                                            <span>Production Dispatch</span>
                                        </a>
                                    </li>
                                @endif
                            @endcan

                            @can('production.traceability.view')
                                @if($rProdTrace)
                                    <li class="nav-item">
                                        <a data-erp-menu-item href="{{ route($rProdTrace, $currentProject) }}"
                                           class="nav-link erp-nav-link d-flex align-items-center px-3 py-1 ps-4
                                                  {{ $isRoute('projects.production-traceability.*') ? 'active' : 'text-body-secondary' }}">
                                            <i class="bi bi-search me-2"></i>
                                            <span>Traceability Search</span>
                                        </a>
                                    </li>
                                @endif
                            @endcan

                        @endcanany
                    @endif
                        </ul>
                    </div>
                </li>
                @endcan
               {{-- =========================
     STORAGE MODULE (User-level access)
     ========================= --}}
	@canany(['storage.view', 'storage.admin'])
    @php
        // Pick whichever route name you finally implement
        $rStorageHome   = $pickRoute(['storage.folders.index', 'storage.index']);
        $rStorageShared = $pickRoute(['storage.shared.index', 'storage.shared']);
    @endphp

    <li class="nav-item erp-sidebar-section mb-1">
        <button type="button"
                class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $secId('storage') }}"
                aria-expanded="{{ $openStorage ? 'true' : 'false' }}"
                aria-controls="{{ $secId('storage') }}"
                data-erp-section-toggle
                data-erp-section-key="storage">
            <span class="d-flex align-items-center gap-2">
                <i class="bi bi-folder2-open"></i>
                <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Storage</span>
            </span>
            <i class="bi bi-chevron-down erp-chevron"></i>
        </button>

        <div id="{{ $secId('storage') }}" class="collapse {{ $openStorage ? 'show' : '' }}">
            <ul class="nav flex-column small mt-1">
                <li class="nav-item">
                    <a data-erp-menu-item
                       href="{{ $rStorageHome ? route($rStorageHome) : '#' }}"
                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                              {{ $isRoute('storage.*') ? 'active' : 'text-body-secondary' }}">
                        <i class="bi bi-folder me-2"></i>
                        <span>My Storage</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a data-erp-menu-item
                       href="{{ $rStorageShared ? route($rStorageShared) : '#' }}"
                       class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                              {{ $isRoute('storage.shared.*') ? 'active' : 'text-body-secondary' }}">
                        <i class="bi bi-people me-2"></i>
                        <span>Shared with Me</span>
                    </a>
                </li>
            </ul>
        </div>
    </li>
	@endcanany
          
              
              	@canany(['tasks.view', 'tasks.list.view'])
                <li class="nav-item erp-sidebar-section mb-1">
                    <button type="button" class="btn btn-sm w-100 text-start d-flex align-items-center justify-content-between border-0 rounded-3 px-3 py-2 erp-accordion-header"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $secId('tasks') }}"
                            aria-expanded="{{ $openTasks ? 'true' : 'false' }}"
                            aria-controls="{{ $secId('tasks') }}"
                            data-erp-section-toggle
                            data-erp-section-key="tasks">
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-list-task"></i>
                            <span class="text-uppercase fw-semibold" style="font-size: 0.75rem;">Task Management</span>
                        </span>
                        <i class="bi bi-chevron-down erp-chevron"></i>
                    </button>
                    <div id="{{ $secId('tasks') }}" class="collapse {{ $openTasks ? 'show' : '' }}">
                        <ul class="nav flex-column small mt-1">
                    @can('tasks.view')
                        @if(\Illuminate\Support\Facades\Route::has('tasks.my-tasks'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('tasks.my-tasks') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('tasks.my-tasks') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-person-check me-2"></i>
                                    <span>My Tasks</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('tasks.view')
                        @if(\Illuminate\Support\Facades\Route::has('tasks.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('tasks.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('tasks.index') || $isRoute('tasks.show') || $isRoute('tasks.edit') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-list-task me-2"></i>
                                    <span>All Tasks</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('tasks.view')
                        @if(\Illuminate\Support\Facades\Route::has('task-board.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('task-board.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('task-board.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-kanban me-2"></i>
                                    <span>Task Board</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('tasks.list.view')
                        @if(\Illuminate\Support\Facades\Route::has('task-lists.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('task-lists.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('task-lists.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-folder me-2"></i>
                                    <span>Task Lists</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('tasks.create')
                        @if(\Illuminate\Support\Facades\Route::has('tasks.create'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('tasks.create') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('tasks.create') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-plus-circle me-2"></i>
                                    <span>New Task</span>
                                </a>
                            </li>
                        @endif
                    @endcan

                    @can('tasks.settings.view')
                        @if(\Illuminate\Support\Facades\Route::has('task-settings.statuses.index'))
                            <li class="nav-item">
                                <a data-erp-menu-item href="{{ route('task-settings.statuses.index') }}"
                                   class="nav-link erp-nav-link d-flex align-items-center px-3 py-1
                                          {{ $isRoute('task-settings.*') ? 'active' : 'text-body-secondary' }}">
                                    <i class="bi bi-gear me-2"></i>
                                    <span>Task Settings</span>
                                </a>
                            </li>
                        @endif
                    @endcan
                        </ul>
                    </div>
                </li>
                @endcanany

            </ul>
        </div>
    </div>
</nav>
