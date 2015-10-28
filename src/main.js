var React = require('react');
var ReactDOM = require('react-dom');
var Button = require('react-bootstrap').Button;
var DropdownButton = require('react-bootstrap').DropdownButton;
var MenuItem = require('react-bootstrap').MenuItem;
var Modal = require('react-bootstrap').Modal;
var Input = require('react-bootstrap').Input;
var ButtonInput = require('react-bootstrap').ButtonInput;
var Breadcrumb = require('react-bootstrap').Breadcrumb;
var BreadcrumbItem = require('react-bootstrap').BreadcrumbItem;
var moment = require('moment');

var FileList = React.createClass({
    getInitialState: function() {
        return {numberSelected: 0};
    },
    render: function() {
        var items = this.props.items.map(function(item) {
            return (<Item key={item.path} item={item} onOpenFolder={this.props.onOpenFolder} />);
        }.bind(this));

        if (this.state.numberSelected > 0) {
            var header = (
                <tr>
                    <th>{this.state.numberSelected} bestanden geselecteerd.</th>
                </tr>
            );
        } else {
            var header = (
                <tr>
                    <th>Naam</th>
                    <th>Laatst gewijzigd</th>
                    <th>Gedeeld met</th>
                </tr>
            );
        }

        return (
            <table className="table table-hover">
            <thead>
                {header}
            </thead>
            <tbody>
                {items}
            </tbody>
            </table>
        );
    }
});

var Item = React.createClass({
    getInitialState: function() {
        return {selected: false};
    },
    componentWillUpdate: function() {
        if (typeof this.props.onChange === 'function') {
            this.props.onChange();
        }
    },
    openFolder: function() {
        this.props.onOpenFolder(this.props.item.path);
    },
    render: function() {
        var cssClass = this.state.selected ? 'active' : '';
        var sharedWith = _appData['accessIds'][this.props.item['shared_with']];

        if (this.props.item['is_dir']) {
            return (
                <tr onClick={this.handleClick} className={cssClass}>
                    <td>
                        <a href="javascript:void(0);" onClick={this.openFolder}>
                            <span className="glyphicon glyphicon-folder-close"></span>&nbsp;
                            {this.props.item.title}
                        </a>
                    </td>
                    <td>-</td>
                    <td>{sharedWith}</td>
                </tr>
            );
        } else {
            var modified_at = moment.unix(this.props.item['modified_at']).format("DD-MM-YY HH:mm");

            return (
                <tr onClick={this.handleClick} className={cssClass}>
                    <td>
                        <a href="javascript:void(0);">
                            <span className="glyphicon glyphicon-file"></span>&nbsp;
                            {this.props.item.title}
                        </a>
                    </td>
                    <td>{modified_at}</td>
                    <td>{sharedWith}</td>
                </tr>
            );
        }
    },
    handleClick: function() {
        this.setState({selected: !this.state.selected});
    }
});

var FileUpload = React.createClass({
    getInitialState() { return { showModal: false, files: null }; },
    close() { this.setState({ showModal: false }); },
    open() { this.setState({ showModal: true }); },
    render() {
        return (
            <div>
                <Modal show={this.state.showModal} onHide={this.close}>
                    <Modal.Header closeButton>
                        <Modal.Title>Upload een bestand</Modal.Title>
                    </Modal.Header>
                    <Modal.Body>
                        <form onSubmit={this.upload}>
                            <Input type="file" multiple label="Bestand(en)" name="files" onChange={this.changeFiles} />
                            <ButtonInput type="submit" bsStyle="primary" value="Uploaden" />
                        </form>
                    </Modal.Body>
                </Modal>
            </div>
        )
    },
    changeFiles(e) {
        this.setState({files: e.target.files[0]});
    },
    upload(e) {
        e.preventDefault();

        console.log(this.state.files);
        for (var i=0; i < this.state.files.length; i++) {
            $jq19.ajax({
                method: 'POST',
                url: '/files/96292/' + this.state.files[i].name,
                data: this.state.files[i],
                processData: false
            });
        }

        //this.close();
    }
});

var FolderCreate = React.createClass({
    getInitialState() { return { showModal: false, title:'', accessId:'96292' }; },
    close() { this.setState({ showModal: false }); },
    open() { this.setState({ showModal: true }); },
    render() {

        var accessOptions = $jq19.map(_appData['accessIds'], function(key, value) {
            return (<option key={key}>{key}</option>);
        });

        return (
            <div>
                <Modal show={this.state.showModal} onHide={this.close}>
                    <Modal.Header closeButton>
                        <Modal.Title>Maak een map</Modal.Title>
                    </Modal.Header>
                    <Modal.Body>
                        <form onSubmit={this.create} enctype="multipart/form-data">
                            <Input type="text" ref="title" label="Title" value={this.state.title} onChange={this.changeTitle} />
                            <Input type="select" ref="accessId" label="Toegang" value={this.state.accessId} onChange={this.changeAccessId}>
                                {accessOptions}
                            </Input>
                            <ButtonInput type="submit" bsStyle="primary" value="Aanmaken" />
                        </form>
                    </Modal.Body>
                </Modal>
            </div>
        )
    },
    changeTitle(e) {
        this.setState({title: e.target.value});
    },
    changeAccessId(e) {
        this.setState({accessId: e.target.value});
    },
    create(e) {
        e.preventDefault();
        this.close();

        $jq19.ajax({
            method: 'POST',
            url: '/lox_api/operations/create_folder',
            data: {
                'path': this.props.path + '/' + this.state.title
            },
            success: function(data) {
                this.setState({
                    'title':''
                });
                this.props.onComplete();
            }.bind(this)
        });
    }
});

var FileBrowser = React.createClass({
    getInitialState: function() {
        return {
            path: '/28712902',
            items: []
        }
    },
    componentDidMount: function() {
        this.getItems();
    },
    getItems: function() {
        $jq19.ajax({
            url: '/lox_api/meta' + this.state.path,
            dataType: 'json',
            success: function(data) {
                this.setState({items: data.children});
            }.bind(this)
        });
    },
    openFolder: function(path) {
        this.state.path = path;
        this.getItems();
    },
    toHome: function() {
        this.state.path = this.props.home;
        this.getItems();
    },
    render: function() {
        var path = this.state.path;
        if (path[0] == '/') { path = path.slice(1); }
        if (path[-1] == '/') { path = path.slice(0, -1); }

        var crumbs = path.split('/');
        var breadcrumb = crumbs.map(function(crumb) {

            var name = _appData['folderNames'][crumb];
            return (<BreadcrumbItem key={crumb}>{name}</BreadcrumbItem>);
        });

        return (
            <div>
                <div className="pleiobox-breadcrumb">
                    <Breadcrumb>
                        <BreadcrumbItem href="javascript:void(0);" onClick={this.toHome}>
                            Home
                        </BreadcrumbItem>
                        {breadcrumb}
                    </Breadcrumb>
                </div>
                <div className="pleiobox-btn-group">
                    <DropdownButton id="new" title="Toevoegen" pullRight={true}>
                        <MenuItem onClick={this.fileNew}>Nieuw bestand</MenuItem>
                        <MenuItem onClick={this.fileUpload}>Bestand uploaden</MenuItem>
                        <MenuItem onClick={this.folderCreate}>Nieuwe map</MenuItem>
                    </DropdownButton>
                </div>
                <FileList items={this.state.items} onOpenFolder={this.openFolder} />
                <FileUpload ref="fileUpload" />
                <FolderCreate ref="folderCreate" path={this.state.path} onComplete={this.getItems} />
            </div>
        );
    },
    fileNew: function() {
        window.open('/odt_editor/create' + this.state.path, '_blank');
    },
    fileUpload: function() {
        this.refs['fileUpload'].open();
    },
    folderCreate: function() {
        this.refs['folderCreate'].open();
    }
});

ReactDOM.render(
    <FileBrowser home='/28712902' />,
    document.getElementById('pleiobox')
);