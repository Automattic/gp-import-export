<?php
/**
 * This script enables granting and removing of rights for bulk import and bulk export
 */
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/gp-load.php';

class Grant_Bulk_Permissions extends GP_CLI {
	var $short_options = 'u:p:g:r:';

	var $usage = "-u <username> -p <project-path> ( -g <grant-permission>] | [-r <revoke-permission> )

Supported permission are:
 - bulk-import
 - bulk-export
";

	function run() {
		if ( ! isset( $this->options['u'] )
			|| ( ! isset( $this->options['g'] ) && ! isset( $this->options['r'] ) )
		) {
			$this->usage();
		}
		$user = GP::$user->get( $this->options['u'] );
		if ( !$user || !$user->id ) $this->error( __('User not found!') );

		$project = GP::$project->by_path( $this->options['p'] );
		if ( !$project ) $this->error( __('Project not found!') );

		$grants = gp_array_get( $this->options, 'g' );
		if ( $grants ) {
			if ( !is_array( $grants ) ) {
				$grants = array( $grants );
			}

			foreach ( $grants as $permission_name ) {
				if ( ! in_array( $permission_name, array( 'bulk-import', 'bulk-export') ) ) {
					echo "Invalid permission: $permission_name\n";
					continue;
				}
				$permission = GP::$permission->find_one( $this->get_permission_array( $user, $project, $permission_name ) );
				if ( ! $permission ) {
					GP::$permission->create( $this->get_permission_array( $user, $project, $permission_name ) );
					echo "Permission $permission_name granted to user ", $user->user_login, " for project ", $project->slug, ".\n";
				} else {
					echo "User ", $user->user_login, " already has the permission $permission_name for project ", $project->slug, ".\n";
				}
			}
		}

		$revokes = gp_array_get( $this->options, 'r' );
		if ( $revokes ) {
			if ( !is_array( $revokes ) ) {
				$revokes = array( $revokes );
			}

			foreach ( $revokes as $permission_name ) {
				if ( ! in_array( $permission_name, array( 'bulk-import', 'bulk-export') ) ) {
					echo "Invalid permission: $permission_name\n";
					continue;
				}
				$permission = GP::$permission->find_one( $this->get_permission_array( $user, $project, $permission_name ) );
				if ( $permission ) {
					$permission->delete();
					echo "Permission $permission_name revoked from user ", $user->user_login, " for project ", $project->slug, ".\n";
				} else {
					echo "User ", $user->user_login, " did not have the permission $permission_name for project ", $project->slug, ".\n";
				}
			}
		}
	}

	private function get_permission_array( GP_User $user, GP_Project $project, $permission_name ) {
		return array( 'user_id' => $user->id, 'object_type' => 'project', 'object_id' => $project->id, 'action' => $permission_name );
	}
}

$grant_bulk_permissions = new Grant_Bulk_Permissions;
$grant_bulk_permissions->run();
